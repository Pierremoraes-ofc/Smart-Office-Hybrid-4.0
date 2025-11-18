<?php
/**
 * logout.php — Smart Office 4.0
 * Finaliza a sessão do usuário e exibe uma mensagem multilíngue.
 */
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../api/functions.php';
require_once __DIR__ . '/../api/language.php';

$pdo  = db();
$lang = get_current_language($pdo);



// === Remove todas as variáveis de sessão ===
$_SESSION = [];

// === Destrói o cookie da sessão (caso exista) ===
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

// === Destroi a sessão ===
session_destroy();

// === Cria nova sessão só para o toast ===
session_start();
push_toast_logout('info', $lang->get('logout_titulo'), $lang->get('logout_mensagem'), 3000);

// === Redireciona ao login ===
header('Location: /SmartOfficeHybrid/admin/login.php');
exit;
