<?php
// api/list_columns.php
// Retorna colunas de uma tabela em JSON para o form de gráficos.

require_once __DIR__ . '/../api/functions.php';

header('Content-Type: application/json; charset=utf-8');

try {
    $pdo = db();

    $table = $_GET['table'] ?? '';
    $table = trim($table);

    if ($table === '') {
        echo json_encode(['ok' => false, 'error' => 'Tabela não informada.']);
        exit;
    }

    // Nome de tabela só com letras, números e _
    if (!preg_match('/^[a-zA-Z0-9_]+$/', $table)) {
        echo json_encode(['ok' => false, 'error' => 'Nome de tabela inválido.']);
        exit;
    }

    // Descobre o banco atual
    $dbName = $pdo->query('SELECT DATABASE()')->fetchColumn();
    if (!$dbName) {
        echo json_encode(['ok' => false, 'error' => 'Banco de dados não selecionado.']);
        exit;
    }

    // Busca colunas da tabela
    $sql = "
        SELECT COLUMN_NAME, DATA_TYPE
        FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = :db
          AND TABLE_NAME   = :tbl
        ORDER BY ORDINAL_POSITION
    ";
    $st = $pdo->prepare($sql);
    $st->execute([
        ':db'  => $dbName,
        ':tbl' => $table,
    ]);

    $cols = $st->fetchAll(PDO::FETCH_ASSOC);

    if (!$cols) {
        echo json_encode(['ok' => true, 'columns' => []]);
        exit;
    }

    $result = [];
    foreach ($cols as $c) {
        $result[] = [
            'name' => $c['COLUMN_NAME'],
            'type' => strtolower((string)$c['DATA_TYPE']),
        ];
    }

    echo json_encode(['ok' => true, 'columns' => $result]);
} catch (Throwable $e) {
    echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
}
