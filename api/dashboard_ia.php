<?php
require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/language.php';                 //  Opções de idioma

require_login();
$pdo = db();
$lang = get_current_language($pdo);                   //  Idioma atual
// Aqui você monta as mensagens conforme a opção escolhida (1, 2, 3, 4, 5)
$messages = [
    [
        'role'    => 'system',
        'content' => $lang->get('parametros_ia'),
    ],
    [
        'role'    => 'user',
        'content' => $perguntaDoUsuario // já montada com base na opção escolhida
    ],
];

$result = dso_ia_chat($pdo, $messages);

header('Content-Type: application/json; charset=utf-8');

if (!$result['ok']) {
    echo json_encode([
        'ok'    => false,
        'error' => $result['error'],
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

echo json_encode([
    'ok'     => true,
    'answer' => $result['content'],
], JSON_UNESCAPED_UNICODE);
exit;
