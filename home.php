<?php
/**
 * home.php / index.php
 * Página inicial do Sistema Academia (painel de status)
 */

require_once __DIR__ . '/api/database.php';

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
        'message' => 'Conectado com sucesso.',
        'data'    => ['data_hora' => date('d/m/Y H:i:s', strtotime($serverNow ?: 'now'))]
    ];

    $databaseInfo = [
        'exists' => (bool)$dbExists,
        'name'   => $dbName ?: '(sem banco selecionado)'
    ];

} catch (Throwable $e) {
    // Em qualquer falha, mantém as mesmas chaves que seu HTML já usa
    $connectionTest = ['success' => false, 'message' => 'Falha na conexão: ' . $e->getMessage()];
    $databaseInfo   = ['exists' => false, 'name' => null];
    $tables         = [];
}
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sistema Academia - Página Inicial</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); min-height: 100vh; padding: 20px; }
        .container { max-width: 800px; margin: 0 auto; background: white; border-radius: 15px; box-shadow: 0 20px 40px rgba(0,0,0,0.1); overflow: hidden; }
        .header { background: linear-gradient(45deg, #2c3e50, #3498db); color: white; padding: 30px; text-align: center; }
        .header h1 { font-size: 2.5rem; margin-bottom: 10px; }
        .header p { font-size: 1.1rem; opacity: 0.9; }
        .content { padding: 30px; }
        .status-card { background: #f8f9fa; border-left: 5px solid #28a745; padding: 20px; margin-bottom: 20px; border-radius: 5px; }
        .status-card.error { border-left-color: #dc3545; }
        .status-card h3 { color: #2c3e50; margin-bottom: 10px; }
        .info-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 20px; margin-top: 20px; }
        .info-box { background: #fff; border: 1px solid #dee2e6; border-radius: 8px; padding: 20px; }
        .info-box h4 { color: #495057; margin-bottom: 15px; font-size: 1.1rem; }
        .table-list { list-style: none; max-height: 200px; overflow-y: auto; }
        .table-list li { padding: 5px 10px; margin: 2px 0; background: #e9ecef; border-radius: 4px; font-family: monospace; }
        .status-indicator { display: inline-block; width: 12px; height: 12px; border-radius: 50%; margin-right: 8px; }
        .status-ok { background: #28a745; }
        .status-error { background: #dc3545; }
        .navigation { margin-top: 30px; text-align: center; }
        .nav-button { display: inline-block; padding: 12px 25px; margin: 10px; background: #007bff; color: white; text-decoration: none; border-radius: 5px; transition: all 0.3s ease; }
        .nav-button:hover { background: #0056b3; transform: translateY(-2px); }
        .timestamp { text-align: center; color: #6c757d; margin-top: 20px; font-size: 0.9rem; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>🏋️ Sistema Academia</h1>
            <p>Painel de Status e Navegação</p>
        </div>
        
        <div class="content">
            <!-- Status da Conexão -->
            <div class="status-card <?= $connectionTest['success'] ? '' : 'error' ?>">
                <h3>
                    <span class="status-indicator <?= $connectionTest['success'] ? 'status-ok' : 'status-error' ?>"></span>
                    Status da Conexão com Banco
                </h3>
                <p><strong>Status:</strong> <?= htmlspecialchars($connectionTest['message']) ?></p>
                
                <?php if ($connectionTest['success'] && isset($connectionTest['data'])): ?>
                    <p><strong>Data/Hora do Servidor:</strong> <?= htmlspecialchars($connectionTest['data']['data_hora']) ?></p>
                <?php endif; ?>
            </div>
            
            <!-- Informações do Sistema -->
            <div class="info-grid">
                <div class="info-box">
                    <h4>📊 Informações do Banco</h4>
                    <?php if (!empty($databaseInfo['exists'])): ?>
                        <p><strong>Banco:</strong> <?= htmlspecialchars($databaseInfo['name']) ?></p>
                        <p><strong>Tabelas encontradas:</strong> <?= count($tables) ?></p>
                        <p><strong>Status:</strong> ✅ Ativo</p>
                    <?php else: ?>
                        <p><strong>Status:</strong> ❌ Banco não encontrado</p>
                    <?php endif; ?>
                </div>
                
                <div class="info-box">
                    <h4>🔧 Informações do Servidor</h4>
                    <p><strong>PHP:</strong> <?= PHP_VERSION ?></p>
                    <p><strong>Servidor:</strong> <?= htmlspecialchars($_SERVER['SERVER_SOFTWARE'] ?? 'N/A') ?></p>
                    <p><strong>Host:</strong> <?= htmlspecialchars($_SERVER['HTTP_HOST'] ?? 'localhost') ?></p>
                </div>
            </div>
            
            <!-- Lista de Tabelas -->
            <?php if (!empty($tables)): ?>
                <div class="info-box" style="margin-top: 20px;">
                    <h4>📋 Tabelas do Sistema</h4>
                    <ul class="table-list">
                        <?php foreach ($tables as $table): ?>
                            <li><?= htmlspecialchars($table) ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>
            
            <!-- Navegação -->
            <div class="navigation">
                <h4 style="margin-bottom: 20px;">🧭 Navegação do Sistema</h4>
                
                <a href="admin/" class="nav-button">👨‍💼 Área Administrativa</a>
                <a href="client/" class="nav-button">👤 Área do Cliente</a>
                <a href="install.php" class="nav-button">⚙️ Reinstalar / Configurar</a>
                
                <br>
                
                <a href="admin/clientes/" class="nav-button">👥 Gerenciar Clientes</a>
                <a href="admin/funcionarios/" class="nav-button">👷 Gerenciar Funcionários</a>
                <a href="admin/cursos/" class="nav-button">🎯 Gerenciar Cursos</a>
                <a href="admin/financeiro/" class="nav-button">💰 Financeiro</a>
            </div>
            
            <!-- Timestamp -->
            <div class="timestamp">
                Última atualização: <?= date('d/m/Y H:i:s') ?>
            </div>
        </div>
    </div>
</body>
</html>
