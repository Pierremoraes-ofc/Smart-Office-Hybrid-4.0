<?php
/**
 * install.php — Dashboard Smart Office 4.0 (multi-etapas)
 * Compatível com PHP >= 7.2 (sem propriedades tipadas), MySQL 5.7/MariaDB 10.3+
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

// ===== Helpers de UI (alerts) =====
function ok(string $msg): void
{
  echo '<div class="alert alert-success" role="alert">'
    . htmlspecialchars($msg, ENT_QUOTES, 'UTF-8')
    . '</div>';
}
function fail(string $msg): void
{
  echo '<div class="alert alert-danger" role="alert">'
    . htmlspecialchars('ERRO: ' . $msg, ENT_QUOTES, 'UTF-8')
    . '</div>';
  echo '<p><a class="btn btn-secondary" href="install.php">Voltar</a></p>';
  exit;
}

// ===== Executor de SQL com compat e log do statement que falhou =====
function run_sql_file(PDO $pdo, string $path): bool
{
  if (!is_file($path))
    return false;
  $sql = file_get_contents($path);

  // Compat MySQL:
  // 1) DATE não aceita DEFAULT (CURRENT_DATE) em 5.7
  $sql = str_ireplace("DEFAULT (CURRENT_DATE)", "DEFAULT NULL", $sql);
  // 2) CREATE INDEX IF NOT EXISTS (só 8.0+)
  $sql = str_ireplace("CREATE INDEX IF NOT EXISTS", "CREATE INDEX", $sql);

  // Remove comentários e divide em statements
  $sql = preg_replace('~(--[^\n]*\n)|(/\*.*?\*/)|(#.*?\n)~s', "\n", $sql);
  $statements = array_filter(array_map('trim', explode(';', $sql)));

  foreach ($statements as $st) {
    if ($st === '')
      continue;
    try {
      $pdo->exec($st);
    } catch (PDOException $e) {
      // 1061: Duplicate key name (índice já existe) — ignorar
      if (strpos($e->getMessage(), '1061') !== false)
        continue;
      throw new PDOException($e->getMessage() . "\n--- SQL que falhou ---\n" . $st . "\n----------------------");
    }
  }
  return true;
}

$step = $_GET['step'] ?? 'db';
?>
<!doctype html>
<html lang="pt-BR">

<head>
  <meta charset="utf-8" />
  <title>Instalador — Dashboard Smart Office 4.0</title>
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <!-- Ajuste estes caminhos conforme sua pasta AdminLTE -->
  <link rel="stylesheet" href="AdminLTE/css/adminlte.css" />
  <link rel="stylesheet" href="AdminLTE/plugins/fontawesome-free/css/all.min.css" />
</head>

<body class="layout-fixed sidebar-expand-lg sidebar-open bg-body-tertiary">
  <div class="app-wrapper">
    <main class="app-main">
      <div class="app-content-header">
        <div class="container-fluid">
          <h3 class="mb-0">Instalador — Dashboard Smart Office 4.0</h3>
          <p class="text-muted">Passo: <?php echo htmlspecialchars($step, ENT_QUOTES, 'UTF-8'); ?></p>
        </div>
      </div>
      <div class="app-content">
        <div class="container-fluid">

          <?php
          /******************************
           * STEP 1 — DB CONFIG & SETUP *
           ******************************/
          if ($step === 'db') {

            if ($_SERVER['REQUEST_METHOD'] === 'POST') {
              $db_host = trim($_POST['db_host'] ?? '');
              $db_user = trim($_POST['db_user'] ?? '');
              $db_pass = (string) ($_POST['db_pass'] ?? '');
              $db_name = trim($_POST['db_name'] ?? '');
              $admin_user = trim($_POST['admin_user'] ?? '');
              $admin_pass = (string) ($_POST['admin_pass'] ?? '');

              if ($db_host === '' || $db_user === '' || $db_name === '' || $admin_user === '' || $admin_pass === '') {
                fail('Preencha todos os campos obrigatórios.');
              }

              // 1) Conectar sem selecionar DB
              try {
                $dsnNoDb = "mysql:host={$db_host};charset=utf8mb4";
                $pdo = new PDO($dsnNoDb, $db_user, $db_pass, [
                  PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                  PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                  PDO::ATTR_EMULATE_PREPARES => false,
                ]);
                ok('Conexão ao MySQL estabelecida (sem selecionar DB)');
              } catch (PDOException $e) {
                fail('Falha na conexão inicial: ' . $e->getMessage());
              }

              // 2) Criar DB se não existir
              try {
                $pdo->exec("CREATE DATABASE IF NOT EXISTS `{$db_name}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
                ok("Banco `{$db_name}` criado/verificado com sucesso");
              } catch (PDOException $e) {
                fail('Erro criando banco: ' . $e->getMessage());
              }

              // 3) Conectar no DB
              try {
                $dsnDb = "mysql:host={$db_host};dbname={$db_name};charset=utf8mb4";
                $pdo = new PDO($dsnDb, $db_user, $db_pass, [
                  PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                  PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                  PDO::ATTR_EMULATE_PREPARES => false,
                ]);
                ok('Conectado ao banco de dados'); // (verde)
              } catch (PDOException $e) {
                fail('Erro conectando ao banco: ' . $e->getMessage());
              }

              // 4) Aplicar database.sql (se existir)
              $schemaPath = __DIR__ . '/database.sql';
              if (is_file($schemaPath)) {
                try {
                  run_sql_file($pdo, $schemaPath);
                  ok('Aplicada estrutura do banco de dados (database.sql)');
                } catch (Throwable $e) {
                  fail('Erro aplicando database.sql: ' . $e->getMessage());
                }
              } else {
                ok('Estrutura não encontrada (database.sql ausente) — prosseguindo');
              }

              // 5) Criar dso_users_admin (sem limite) e inserir admin inicial
              try {
                $pdo->exec("
        DROP TABLE IF EXISTS `dso_users_admin`;
        CREATE TABLE IF NOT EXISTS `dso_users_admin` (
          `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
          `username` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
          `password_hash` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
          `first_name` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
          `last_name` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
          `email` varchar(150) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
          `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
          PRIMARY KEY (`id`),
          UNIQUE KEY `username` (`username`)
        ) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
      ");
                ok('Criada tabela dso_users_admin do Dashboard Smart Office 4.0');

                $stmt = $pdo->prepare("SELECT id FROM dso_users_admin WHERE username = :u LIMIT 1");
                $stmt->execute([':u' => $admin_user]);
                if (!$stmt->fetch()) {
                  $hash = password_hash($admin_pass, PASSWORD_DEFAULT);
                  $ins = $pdo->prepare("INSERT INTO dso_users_admin (username, password_hash, email) VALUES (:u, :p, :e)");
                  $ins->execute([':u' => $admin_user, ':p' => $hash, ':e' => null]);
                  ok('Cadastrado usuario Administrador do Dashboard Smart Office 4.0');
                } else {
                  ok('Usuário administrador já existia — mantido');
                }
              } catch (PDOException $e) {
                fail('Erro criando/inserindo admin: ' . $e->getMessage());
              }

              // 6) Criar tabela de configurações DSO (registro único id=1) + default
              try {
                $pdo->exec("
        DROP TABLE IF EXISTS `dso_settings`;
        CREATE TABLE IF NOT EXISTS `dso_settings` (
          `id` int(10) UNSIGNED NOT NULL DEFAULT 1,
          `public_home` tinyint(1) NOT NULL DEFAULT 1,
          `language` varchar(10) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pt_BR',
          `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
          PRIMARY KEY (`id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

      ");
                ok('Criada tabela dso_settings do Dashboard Smart Office 4.0');

                // Garante que exista o registro id=1 com public_home=1
                $pdo->exec("INSERT IGNORE INTO dso_settings (id, public_home) VALUES (1,1)");
                ok('Aplicada configuração padrão (public_home = 1)');
              } catch (PDOException $e) {
                fail('Erro criando/configurando dso_settings: ' . $e->getMessage());
              }

              try {
                $pdo->exec("
      DROP TABLE IF EXISTS `dso_grafico`;
      CREATE TABLE IF NOT EXISTS `dso_grafico` (
        `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
        `nome` varchar(120) COLLATE utf8mb4_unicode_ci NOT NULL,
        `descricao` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
        `tabela` varchar(128) COLLATE utf8mb4_unicode_ci NOT NULL,
        `coluna_grupo` varchar(128) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
        `coluna_valor` varchar(128) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
        `agregacao` enum('COUNT','SUM','AVG','MAX','MIN') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'COUNT',
        `filtro_where` text COLLATE utf8mb4_unicode_ci DEFAULT NULL,
        `ativo` tinyint(1) NOT NULL DEFAULT 1,
        `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
        `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
        PRIMARY KEY (`id`),
        KEY `idx_dso_grafico_tab` (`tabela`,`ativo`)
      ) ENGINE=InnoDB AUTO_INCREMENT=8 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

      ");

                ok('Criada tabela dso_grafico do Dashboard Smart Office 4.0');
              } catch (PDOException $e) {
                fail('Erro ao criar dso_grafico: ' . $e->getMessage());
              }

              try {
                $pdo->exec("
      DROP TABLE IF EXISTS `dso_grafico_join`;
      CREATE TABLE IF NOT EXISTS `dso_grafico_join` (
        `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
        `grafico_id` int(10) UNSIGNED NOT NULL,
        `tabela` varchar(128) COLLATE utf8mb4_unicode_ci NOT NULL,
        `alias` varchar(32) COLLATE utf8mb4_unicode_ci NOT NULL,
        `tipo_join` enum('INNER','LEFT','RIGHT') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'INNER',
        `on_expr` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
        `ativo` tinyint(1) NOT NULL DEFAULT 1,
        `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
        `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
        PRIMARY KEY (`id`),
        KEY `idx_gj_grafico` (`grafico_id`,`ativo`)
      ) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

      ALTER TABLE `dso_grafico_join`
        ADD CONSTRAINT `fk_gj_grafico` FOREIGN KEY (`grafico_id`) REFERENCES `dso_grafico` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

      ");

                ok('Criada tabela dso_grafico_join do Dashboard Smart Office 4.0');
              } catch (PDOException $e) {
                fail('Erro ao criar dso_grafico_join: ' . $e->getMessage());
              }

              try {
                $pdo->exec("
      DROP TABLE IF EXISTS `dso_model_grafico`;
      CREATE TABLE IF NOT EXISTS `dso_model_grafico` (
        `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
        `nome` varchar(120) COLLATE utf8mb4_unicode_ci NOT NULL,
        `descricao` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
        `grafico_id` int(10) UNSIGNED NOT NULL,
        `icone` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
        `info` enum('INFO','ALERT','SUCCESS','DANGER') CHARACTER SET utf8mb4 NOT NULL DEFAULT 'INFO',
        `tipo` enum('KPI-1','KPI-2','BARRA','LINHA','DONUT','AREA') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'BARRA',
        `cor_principal` varchar(16) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
        `cor_secundaria` varchar(16) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
        `exibicao` enum('CARD','FULL','GRANDE','MEDIO','PEQUENO','MINI','MICRO') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'CARD',
        `ordem` int(10) UNSIGNED NOT NULL DEFAULT 1,
        `ativo` tinyint(1) NOT NULL DEFAULT 1,
        `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
        `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
        PRIMARY KEY (`id`),
        KEY `idx_model_ordem` (`ordem`,`ativo`),
        KEY `fk_model_grafico` (`grafico_id`)
      ) ENGINE=InnoDB AUTO_INCREMENT=11 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

      ALTER TABLE `dso_model_grafico`
        ADD CONSTRAINT `fk_model_grafico` FOREIGN KEY (`grafico_id`) REFERENCES `dso_grafico` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;
      COMMIT;
      ");

                ok('Criada tabela dso_model_grafico do Dashboard Smart Office 4.0');
              } catch (PDOException $e) {
                fail('Erro ao criar dso_model_grafico: ' . $e->getMessage());
              }

try {
                $pdo->exec("
      DROP TABLE IF EXISTS `dso_apikey_ia`;
      CREATE TABLE IF NOT EXISTS `dso_apikey_ia` (
        `id` int(11) UNSIGNED NOT NULL AUTO_INCREMENT,
        `apikey` text COLLATE utf8mb4_unicode_ci NOT NULL,
        `reg_date` timestamp NOT NULL DEFAULT current_timestamp(),
        PRIMARY KEY (`id`)
      ) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

      ALTER TABLE `dso_apikey_ia`
        ADD COLUMN `model` ENUM(
          'llama-3.1-8b',        -- Grátis
          'llama-3-8b',          -- Grátis
          'mixtral-8x7b',        -- Grátis

          'llama-3.1-70b',       -- Pago
          'llama-3.3-70b-versatile', -- Pago (substitui o 3.1-70b-versatile que foi descontinuado)
          'gemma-2-27b',         -- Pago
          'mixtral-8x7b-latest', -- Pago
          'llama-3.1-70b-latest' -- Pago
        ) NOT NULL DEFAULT 'llama-3.1-8b'
        AFTER `apikey`;
      ");
                ok('Criada tabela do dso_apikey_ia do Dashboard Smart Office 4.0');
              } catch (PDOException $e) {
                fail('Erro criando tabela dso_apikey_ia: ' . $e->getMessage());
              }















              // 7) Gerar api/database.php (compatível com PHP < 7.4; sem propriedades tipadas)
              $apiDir = __DIR__ . '/api';
              if (!is_dir($apiDir) && !mkdir($apiDir, 0775, true)) {
                fail('Não foi possível criar a pasta /api.');
              }
              $dbPhpPath = $apiDir . '/database.php';

              $databasePhp = "<?php\n"
                . "/** Arquivo gerado automaticamente pelo instalador do Dashboard Smart Office 4.0 */\n"
                . "class Database {\n"
                . "  /** @var string */ private \$host = '" . addslashes($db_host) . "';\n"
                . "  /** @var string */ private \$name = '" . addslashes($db_name) . "';\n"
                . "  /** @var string */ private \$user = '" . addslashes($db_user) . "';\n"
                . "  /** @var string */ private \$pass = '" . addslashes($db_pass) . "';\n"
                . "  /** @var string */ private \$charset = 'utf8mb4';\n"
                . "  /** @var PDO|null */ private \$pdo = null;\n\n"
                . "  /** @return PDO */\n"
                . "  public function getConnection() {\n"
                . "    if (\$this->pdo instanceof PDO) { return \$this->pdo; }\n"
                . "    \$dsn = 'mysql:host='.\$this->host.';dbname='.\$this->name.';charset='.\$this->charset;\n"
                . "    \$opts = array(\n"
                . "      PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,\n"
                . "      PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,\n"
                . "      PDO::ATTR_EMULATE_PREPARES => false,\n"
                . "    );\n"
                . "    \$this->pdo = new PDO(\$dsn, \$this->user, \$this->pass, \$opts);\n"
                . "    return \$this->pdo;\n"
                . "  }\n"
                . "}\n";

              if (file_put_contents($dbPhpPath, $databasePhp) === false) {
                fail('Falha ao escrever api/database.php (permissões?)');
              }
              @chmod($dbPhpPath, 0640);
              ok('Gerado arquivo api/database.php com credenciais seguras');

              // 8) Botão para próximo passo (API Key)
              echo '<a class="btn btn-primary" href="admin/preferences"><i class="fas fa-arrow-right"></i> Prosseguir para configurar as preferências do sistema.</a>';
              echo '<a class="btn btn-primary" href="admin/login"><i class="fas fa-arrow-right"></i> Fazer login</a>';
              echo '<hr/><p class="text-muted">Recomendado: após finalizar a instalação, remova/renomeie este <code>install.php</code> por segurança.</p>';

            } else {
              // Form do passo DB
              ?>
              <div class="card card-secondary card-outline mb-4">
                <div class="card-header">
                  <div class="card-title">1) Conexão com Banco & Criação de Estrutura</div>
                </div>
                <div class="card-body">
                  <form method="post" autocomplete="off">
                    <label class="form-label">Host do Banco</label>
                    <input class="form-control" type="text" name="db_host" placeholder="localhost" required />

                    <label class="form-label mt-3">Usuário do Banco</label>
                    <input class="form-control" type="text" name="db_user" placeholder="root" required />

                    <label class="form-label mt-3">Senha do Banco</label>
                    <input class="form-control" type="password" name="db_pass" placeholder="********" />

                    <label class="form-label mt-3">Nome do Banco</label>
                    <input class="form-control" type="text" name="db_name" placeholder="sistema_academia" required />

                    <hr />
                    <label class="form-label">Usuário Admin (inicial)</label>
                    <input class="form-control" type="text" name="admin_user" placeholder="admin" required />

                    <label class="form-label mt-3">Senha Admin (inicial)</label>
                    <input class="form-control" type="password" name="admin_pass" placeholder="********" required />

                    <button class="btn btn-primary mt-4"><i class="fas fa-database"></i> Instalar</button>
                  </form>
                  <p class="text-muted mt-2">
                    O instalador aplicará o <code>database.sql</code> (se existir), criará <code>dso_users_admin</code>,
                    gerará <code>api/database.php</code> e configurará <code>dso_settings</code> (public_home=1).
                  </p>
                </div>
              </div>
              <?php
            }
          }
          ?>
        </div>
      </div>
    </main>
  </div>
</body>

</html>