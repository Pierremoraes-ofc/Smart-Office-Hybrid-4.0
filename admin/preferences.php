<?php

require_once __DIR__ . '/../api/functions.php';           //  Funções obrigatorias

$pdo = db();                                              //  Conexões ao banco de dados
require_login();                                          //  Verifica se usuário ja esta logado
$settings = get_dso_settings($pdo);                  //  Configurações globais do Smart Office 4.0 registrado na tabela do DB dso_settings
$lang = get_current_language($pdo);                  //  Idioma atual

$public_home_val = (int) $settings['public_home'];
$language_val = (string) $settings['language'];

// Config atual da IA
$iaConfig = dso_get_ia_config($pdo);
$currentModel = $iaConfig['model'] ?? '';
$apikeyMasked = dso_get_apikey_masked($pdo);

// Configura timezone
date_default_timezone_set('America/Sao_Paulo');

// Tenta conectar com o banco e preencher as variáveis que o HTML usa
try {
  // Abre conexão via sua classe
  $pdo = (new Database())->getConnection();

  // Ping simples
  $pdo->query('SELECT 1');

  // Nome do banco selecionado
  $dbName = $pdo->query('SELECT DATABASE()')->fetchColumn();
  $dbExists = !empty($dbName);

  // Data/hora do servidor SQL
  $serverNow = $pdo->query('SELECT NOW()')->fetchColumn();

  // Lista de tabelas do schema atual
  $tables = [];
  if ($dbExists) {
    $stmt = $pdo->prepare("
            SELECT TABLE_NAME 
            FROM information_schema.TABLES 
            WHERE TABLE_SCHEMA = :db
            ORDER BY TABLE_NAME
        ");
    $stmt->execute([':db' => $dbName]);
    $tables = array_column($stmt->fetchAll(PDO::FETCH_ASSOC), 'TABLE_NAME');
  }

  // Monta os objetos exatamente como seu HTML espera
  $connectionTest = [
    'success' => true,
    'message' => $lang->get('connection_success'),
    'data' => ['data_dia' => date('d/m/Y H:i:s', strtotime($serverNow ?: 'now'))]
  ];

  $databaseInfo = [
    'exists' => (bool) $dbExists,
    'name' => $dbName ?: $lang->get('db_exist')
  ];

} catch (Throwable $e) {
  // Em qualquer falha, mantém as mesmas chaves que seu HTML já usa
  $connectionTest = ['success' => false, 'message' => $lang->get('connection_fail') . $e->getMessage()];
  $databaseInfo = ['exists' => false, 'name' => null];
  $tables = [];
}




// CSRF compartilhado com a página
$CSRF = dso_csrf_ensure('csrf_pref');

// POST: salvar dso_settings
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'settings') {
  dso_handle_post_settings($pdo, $_POST, $lang); // salva + toast + redirect
  exit;
}

// carregar valores atuais
$settings = dso_get_settings($pdo);
$public_home_val = (int) $settings['public_home'];
$language_val = (string) $settings['language'];


// POST apenas para a API I.A.
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'apikey') {
  dso_handle_post_apikey($pdo, $_POST, $lang); // valida, salva e redireciona
  exit;
}

// Para exibir máscara no formulário
$apikeyMasked = dso_get_apikey_masked($pdo);

?>

<!doctype html>
<html lang="en">
<!--begin::Head-->

<head>

  <title><?= $lang->get('preferencias'); ?> | Smart Office 4.0</title>
  <?php include 'includes/header.php'; ?>
</head>

<body class="layout-fixed sidebar-expand-lg sidebar-open bg-body-tertiary">
  <!--begin::App Wrapper-->
  <div class="app-wrapper">

    <?php include 'includes/menu.php'; ?>


    <!--begin::App Main-->
    <main class="app-main">
      <div class="app-content">
        <div class="container-fluid">
          <!-- CARDS -->
          <div class="row">

            <!-- Pagina atual -->
            <div class="col-sm-6">
              <h3 class="mb-0"><?= $lang->get('list_database'); ?></h3>
            </div>
            <div class="col-sm-6">
              <ol class="breadcrumb float-sm-end">
                <li class="breadcrumb-item active" aria-current="page"><?= $lang->get('list_database'); ?></li>
              </ol>
            </div>


            <?php include 'includes/cards.php'; ?>
            <!-- Pagina atual -->
          </div>
          <!-- CARDS -->

          <div class="row">
            <!-- Configurações do servidor -->
            <div class="col-md-6">
              <div class="card card-primary card-outline mb-4">
                <div class="card-header">
                  <div class="card-title"><?= $lang->get('config_server'); ?></div>
                </div>
                <form method="post" action="">
                  <input type="hidden" name="_csrf" value="<?= htmlspecialchars($CSRF) ?>">
                  <input type="hidden" name="action" value="settings">

                  <div class="card-body">
                    <div class="mb-3">
                      <label for="homepage" class="form-label"><?= $lang->get('config_home'); ?></label>
                      <select class="form-select" id="homepage" name="public_home" required>
                        <option value="1" <?= $public_home_val === 1 ? 'selected' : '' ?>>
                          <?= $lang->get('config_exibir_home'); ?>
                        </option>
                        <option value="0" <?= $public_home_val === 0 ? 'selected' : '' ?>>
                          <?= $lang->get('config_nao_exibir'); ?>
                        </option>
                      </select>
                      <div class="form-text"><?= $lang->get('config_nao_exibir_help'); ?></div>
                    </div>

                    <div class="mb-3">
                      <label for="idioma" class="form-label"><?= $lang->get('config_idioma'); ?></label>
                      <select class="form-select" id="idioma" name="language" required>
                        <option value="pt_BR" <?= $language_val === 'pt_BR' ? 'selected' : '' ?>>
                          <?= $lang->get('config_ptbr'); ?>
                        </option>
                        <option value="en_US" <?= $language_val === 'en_US' ? 'selected' : '' ?>>
                          <?= $lang->get('config_enus'); ?>
                        </option>
                        <option value="es_ES" <?= $language_val === 'es_ES' ? 'selected' : '' ?>>
                          <?= $lang->get('config_eses'); ?>
                        </option>
                      </select>
                      <div class="form-text"><?= $lang->get('config_idioma_help'); ?></div>
                    </div>
                  </div>

                  <div class="card-footer">
                    <button type="submit" class="btn btn-primary"><?= $lang->get('config_salva'); ?></button>
                  </div>
                </form>


              </div>
            </div>
            <!-- Configurações do servidor -->

            <!-- Configurações da api i.a. -->
            <div class="col-md-6">
              <div class="card card-primary card-outline mb-4">
                <div class="card-header">
                  <div class="card-title"><?= $lang->get('config_server'); ?></div>
                </div>
                <form method="post" action="">
                  <input type="hidden" name="_csrf" value="<?= htmlspecialchars($CSRF) ?>">
                  <input type="hidden" name="action" value="apikey">

                  <div class="card-body">
                    <div class="mb-3">
                      <label for="inicialApiIA" class="form-label"><?= $lang->get('config_api_ia'); ?></label>
                      <input type="text" class="form-control" id="inicialApiIA" name="apikey"
                        placeholder="<?= htmlspecialchars($lang->get('config_cole_apikey')) ?>" value="" />
                      <?php if ($apikeyMasked): ?>
                        <small class="text-muted d-block mt-1">
                          <?= $lang->get('config_atual_mascarada') ?>
                          <code><?= htmlspecialchars($apikeyMasked) ?></code>
                        </small>
                      <?php else: ?>
                        <small class="text-muted d-block mt-1">
                          <?= $lang->get('config_api_ia_help'); ?>
                        </small>
                      <?php endif; ?>
                    </div>

                    <div class="mb-3">
                      <label for="iaModel" class="form-label">
                        <?= $lang->get('config_api_ia_model'); ?>
                      </label>
                      <select class="form-select" id="iaModel" name="model">
                        <option value="">
                          <?= $lang->get('config_model_mantem_atual'); ?>
                        </option>

                        <option value="llama-3.1-8b" <?= $currentModel === 'llama-3.1-8b' ? 'selected' : '' ?>>
                          llama-3.1-8b
                        </option>
                        <option value="llama-3-8b" <?= $currentModel === 'llama-3-8b' ? 'selected' : '' ?>>
                          llama-3-8b
                        </option>
                        <option value="mixtral-8x7b" <?= $currentModel === 'mixtral-8x7b' ? 'selected' : '' ?>>
                          mixtral-8x7b
                        </option>
                        <option value="llama-3.1-70b" <?= $currentModel === 'llama-3.1-70b' ? 'selected' : '' ?>>
                          llama-3.1-70b
                        </option>
                        <option value="llama-3.3-70b-versatile" <?= $currentModel === 'llama-3.3-70b-versatile' ? 'selected' : '' ?>>
                          llama-3.3-70b-versatile
                        </option>
                        <option value="gemma-2-27b" <?= $currentModel === 'gemma-2-27b' ? 'selected' : '' ?>>
                          gemma-2-27b
                        </option>
                        <option value="mixtral-8x7b-latest" <?= $currentModel === 'mixtral-8x7b-latest' ? 'selected' : '' ?>>
                          mixtral-8x7b-latest
                        </option>
                        <option value="llama-3.1-70b-latest" <?= $currentModel === 'llama-3.1-70b-latest' ? 'selected' : '' ?>>
                          llama-3.1-70b-latest
                        </option>
                      </select>

                      <div class="form-text">
                        <?= $lang->get('config_api_ia_model_help'); ?>
                      </div>
                    </div>
                  </div>

                  <div class="card-footer">
                    <button type="submit" class="btn btn-primary"><?= $lang->get('config_salva'); ?></button>
                  </div>
                </form>

              </div>
            </div>
            <!-- Configurações da api i.a. -->

          </div>
        </div>
    </main>

    <!-- Div de fechamento fica no footer.php -->
    <!--</div>-->
    <?php include 'includes/footer.php'; ?>
    <!-- Div de fechamento fica no footer.php -->
</body>

</html>