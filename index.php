<?php

if (session_status() === PHP_SESSION_NONE) { session_start(); }

// Descobre o base path da pasta do app (ex.: "/SmartOfficeHybrid/")
$BASE_PATH = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/\\') . '/';
if ($BASE_PATH === '//') { $BASE_PATH = '/'; } // caso raro no root

// Caminhos físicos
$ROOT = __DIR__;
$hasDatabase = is_file($ROOT . '/api/database.php');

// 1) Se ainda não configurado, manda para o instalador desta mesma pasta
if (!$hasDatabase) {
  header('Location: ' . $BASE_PATH . 'install.php', true, 302);
  exit;
}

// 2) Carrega funções e settings (modelo "registro único" dso_settings)
require_once $ROOT . '/api/functions.php';


$pdo     = db();
$config  = get_dso_settings($pdo);
$showHome = !empty($config['public_home']); // 1 = mostrar landing

// 3) Se PUBLIC HOME estiver ativo e existir home.php → inclui
if ($showHome && is_file($ROOT . '/home.php')) {
  require $ROOT . '/home.php';
  exit;
}

// 4) Caso contrário, vai para o admin (login decide se mostra dashboard)
header('Location: ' . $BASE_PATH . 'admin/login', true, 302);
exit;
