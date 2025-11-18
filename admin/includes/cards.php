<?php

require_once __DIR__ . '/../../api/functions.php';           //  Funções obrigatorias

$pdo      = db();                                         //  Conexões ao banco de dados
require_login();                                          //  Verifica se usuário ja esta logado
$settings = get_dso_settings($pdo);                  //  Configurações globais do Smart Office 4.0 registrado na tabela do DB dso_settings
$lang     = get_current_language($pdo);              //  Idioma atual


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
        'data'    => ['data_dia' => date('d/m/Y H:i:s', strtotime($serverNow ?: 'now'))]
    ];

    $databaseInfo = [
        'exists' => (bool)$dbExists,
        'name'   => $dbName ?: $lang->get('db_exist')
    ];

} catch (Throwable $e) {
    // Em qualquer falha, mantém as mesmas chaves que seu HTML já usa
    $connectionTest = ['success' => false, 'message' => $lang->get('connection_fail') . $e->getMessage()];
    $databaseInfo   = ['exists' => false, 'name' => null];
    $tables         = [];
}
?>

<!-- Conexão com o banco -->
<div class="col-md-3 col-sm-6 col-12">
<div class="info-box text-bg-primary">
    <span class="info-box-icon"> <i class="bi bi-plugin"></i> </span>
    <div class="info-box-content">
    <span class="info-box-text"><?= $lang->get('connection'); ?></span>
    <span class="info-box-number"><?= htmlspecialchars($connectionTest['message']) ?></span>
    <div class="progress"><div class="progress-bar" style="width: 100%"></div></div>
    <span class="progress-description"><?= $lang->get('data') . htmlspecialchars($connectionTest['data']['data_dia']) ?> </span>
    </div>
</div>
</div>
<!-- Conexão com o banco -->              

<!-- Banco de dados -->
<div class="col-md-3 col-sm-6 col-12">
<div class="info-box text-bg-success">
    <span class="info-box-icon"> <i class="bi bi-database"></i> </span>
    <div class="info-box-content">
    <span class="info-box-text"><?= $lang->get('database'); ?></span>
    <span class="info-box-number"><?= count($tables) ?></span>
    <div class="progress"><div class="progress-bar" style="width: <?= count($tables) ?>%"></div></div>
    <span class="progress-description"> <?= $lang->get('db_name') . htmlspecialchars($databaseInfo['name']) ?> </span>
    </div>
</div>
</div>
<!-- Banco de dados -->

<!-- Informaçoes do servidor -->
<div class="col-md-3 col-sm-6 col-12">
<div class="info-box text-bg-warning">
    <span class="info-box-icon"> <i class="bi bi-house"></i> </span>
    <div class="info-box-content">
    <span class="info-box-text"><?= $lang->get('server_info'); ?></span>
    <span class="info-box-number"><?= $lang->get('host') . htmlspecialchars($_SERVER['HTTP_HOST'] ?? 'localhost') ?></span>
    <div class="progress"><div class="progress-bar" style="width: 70%"></div></div>
    <span class="progress-description"> <?= $lang->get('server_version') . htmlspecialchars($_SERVER['SERVER_SOFTWARE'] ?? 'N/A') ?> </span>
    </div>
</div>
</div>
<!-- Informaçoes do servidor -->

<!-- -- >
<div class="col-md-3 col-sm-6 col-12">
<div class="info-box text-bg-danger">
    <span class="info-box-icon"> <i class="bi bi-chat-text-fill"></i> </span>
    <div class="info-box-content">
    <span class="info-box-text">Comments</span>
    <span class="info-box-number">41,410</span>
    <div class="progress"><div class="progress-bar" style="width: 70%"></div></div>
    <span class="progress-description"> 70% Increase in 30 Days </span>
    </div>
</div>
</div>

<div class="col-lg-3 col-6">                
<div class="small-box text-bg-primary">
    <div class="inner">
    <h3>150</h3>
    <p>New Orders</p>
    </div>
    <svg
    class="small-box-icon"
    fill="currentColor"
    viewBox="0 0 24 24"
    xmlns="http://www.w3.org/2000/svg"
    aria-hidden="true"
    >
    <path
        d="M2.25 2.25a.75.75 0 000 1.5h1.386c.17 0 .318.114.362.278l2.558 9.592a3.752 3.752 0 00-2.806 3.63c0 .414.336.75.75.75h15.75a.75.75 0 000-1.5H5.378A2.25 2.25 0 017.5 15h11.218a.75.75 0 00.674-.421 60.358 60.358 0 002.96-7.228.75.75 0 00-.525-.965A60.864 60.864 0 005.68 4.509l-.232-.867A1.875 1.875 0 003.636 2.25H2.25zM3.75 20.25a1.5 1.5 0 113 0 1.5 1.5 0 01-3 0zM16.5 20.25a1.5 1.5 0 113 0 1.5 1.5 0 01-3 0z"
    ></path>
    </svg>
    <a
    href="#"
    class="small-box-footer link-light link-underline-opacity-0 link-underline-opacity-50-hover"
    >
    More info <i class="bi bi-link-45deg"></i>
    </a>
</div>
</div>

<div class="col-12 col-sm-6 col-md-3">
<div class="info-box">
    <span class="info-box-icon text-bg-primary shadow-sm">
    <i class="bi bi-gear-fill"></i>
    </span>
    <div class="info-box-content">
    <span class="info-box-text">CPU Traffic</span>
    <span class="info-box-number">
        10
        <small>%</small>
    </span>
    </div>
</div>
</div>
<! -- -->