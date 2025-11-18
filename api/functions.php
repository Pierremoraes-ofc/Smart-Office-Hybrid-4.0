<?php
// api/functions.php
// Funções utilitárias de BD para o Smart Office 4.0

if (!defined('SMARTOFFICE_FUN')) define('SMARTOFFICE_FUN', true);

require_once __DIR__ . '/database.php';                 //  Conexão ao Banco de dados
require_once __DIR__ . '/language.php';                 //  Opções de idioma

/** Retorna uma conexão PDO usando a classe Database */
function db(): PDO {
    static $pdo = null;
    if ($pdo instanceof PDO) return $pdo;
    $db = new Database();
    $pdo = $db->getConnection();
    return $pdo;
}

/** Garante a existência da tabela dso_users_admin (sem limite de usuários) */
function ensure_users_admin_table(PDO $pdo = null): void {
    $pdo = $pdo ?: db();
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS dso_users_admin (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            username VARCHAR(50) NOT NULL UNIQUE,
            password_hash VARCHAR(255) NOT NULL,
            first_name VARCHAR(100) NULL,
            last_name  VARCHAR(100) NULL,
            email      VARCHAR(150) NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
}

/** Cria usuário admin (retorna id) */function create_admin(string $username, string $password, ?string $email = null, PDO $pdo = null): int {
    $pdo = $pdo ?: db();
    ensure_users_admin_table($pdo);
    $hash = password_hash($password, PASSWORD_DEFAULT);
    $ins  = $pdo->prepare("INSERT INTO dso_users_admin (username, password_hash, email) VALUES (:u,:p,:e)");
    $ins->execute([':u'=>$username, ':p'=>$hash, ':e'=>$email]);
    return (int)$pdo->lastInsertId();
}

/** Busca usuário por username */
function find_user_by_username(string $username, PDO $pdo = null): ?array {
    $pdo = $pdo ?: db();
    ensure_users_admin_table($pdo);
    $stmt = $pdo->prepare("SELECT id, username, password_hash FROM dso_users_admin WHERE username = :u LIMIT 1");
    $stmt->execute([':u'=>$username]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    return $row ?: null;
}

/** Autentica por username/senha; retorna array com 'ok' e 'user' */
function authenticate(string $username, string $password, PDO $pdo = null): array {
    $pdo = $pdo ?: db();
    $user = find_user_by_username($username, $pdo);
    if ($user && password_verify($password, $user['password_hash'])) {
        return ['ok'=>true, 'user'=>$user];
    }
    return ['ok'=>false, 'user'=>null];
}

/** (Opcional) cria admin padrão se não existir ninguém — útil em dev */
function bootstrap_default_admin(PDO $pdo = null): void {
    $pdo = $pdo ?: db();
    ensure_users_admin_table($pdo);
    $count = (int)$pdo->query("SELECT COUNT(*) FROM dso_users_admin")->fetchColumn();
    if ($count === 0) {
        create_admin('admin', '123456', 'admin@localhost', $pdo);
    }
}


function Logged_in(): void {
    static $checked = false;
    if ($checked) return;
    $checked = true;

    if (session_status() === PHP_SESSION_NONE) session_start();
    if (!empty($_SESSION['user_id'])) {
        //header('Location: /SmartOfficeHybrid/admin/dashboard.php');
        dso_redirect('/SmartOfficeHybrid/admin/dashboard.php', 4500, 'Aguarde...', 'Processando...');
    }
}
function require_login(): void {
    static $checked = false;
    if ($checked) return;
    $checked = true;

    if (session_status() === PHP_SESSION_NONE) session_start();
    if (empty($_SESSION['user_id'])) {
        //header('Location: /SmartOfficeHybrid/admin/login.php');
        //exit;
        dso_redirect('/SmartOfficeHybrid/admin/login.php', 4500, 'Aguarde...', 'Processando...');
    }
}






/**
 * Redirecionamento com tela intermediária (toasts + delay)
 * --------------------------------------------------------
 * Exemplo:
 *    dso_redirect('/SmartOfficeHybrid/admin/dashboard.php', 4500);
 * Mostra os toasts pendentes, espera X ms e só então redireciona.
 */
function dso_redirect(string $targetUrl, int $delayMs = 3000, string $title = null, string $message = null): void {
  if (session_status() === PHP_SESSION_NONE) session_start();

  // Guarda o destino e o delay na sessão
  $_SESSION['redirect_target'] = $targetUrl;
  $_SESSION['redirect_delay']  = $delayMs;

  // Toast opcional (para o caso de "Salvo com sucesso" etc)
  if ($title || $message) {
    //push_toast('info', $title ?? 'Aguarde...', $message ?? 'Redirecionando...', $delayMs);
  }

  // Envia o usuário à página intermediária
  header('Location: /SmartOfficeHybrid/admin/login_redirect.php');
  exit;
}








/* 
===============================
   CONFIGURAÇÕES DO DASHBOARD (DSO_SETTINGS)
=============================== 
*/
function ensure_dso_settings_table(PDO $pdo = null): void {
    $pdo = $pdo ?: db();
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS dso_settings (
            id INT UNSIGNED PRIMARY KEY DEFAULT 1,
            public_home TINYINT(1) NOT NULL DEFAULT 1,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    // garante que sempre exista o registro id=1
    $pdo->exec("INSERT IGNORE INTO dso_settings (id, public_home) VALUES (1,1)");
}

/**
 * Obtém as configurações globais do Smart Office 4.0
 * Retorna array ['public_home'=>bool, 'language'=>'pt_BR', ...]
 */
function get_dso_settings(PDO $pdo): array {
    static $cache = null;
    if ($cache !== null) return $cache;

    $stmt = $pdo->query("SELECT * FROM dso_settings WHERE id = 1 LIMIT 1");
    $settings = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];

    // valores padrão se estiver vazio
    $cache = [
        'public_home' => (int)($settings['public_home'] ?? 1),
        'language'    => $settings['language'] ?? 'pt_BR',
        'updated_at'  => $settings['updated_at'] ?? null,
    ];
    return $cache;
}

function set_dso_setting(string $field, $value, PDO $pdo = null): void {
    $pdo = $pdo ?: db();
    ensure_dso_settings_table($pdo);
    $allowed = ['public_home']; // segurança — define campos que podem ser alterados
    if (!in_array($field, $allowed)) return;
    $stmt = $pdo->prepare("UPDATE dso_settings SET {$field} = :v WHERE id = 1");
    $stmt->execute([':v' => (int)$value]);
}

function get_current_language(PDO $pdo) {
  $settings = get_dso_settings($pdo);
  $code     = $settings['language'] ?? 'pt_BR';

  static $cache = null, $last = null;
  if ($cache && $last === $code) return $cache;

  $cache = new LanguageManager($code);
  $last  = $code;
  return $cache;
}

//  Helpers de toast
function push_toast(string $type, string $title, string $message, int $delayMs = 3000): void
{
  if (!isset($_SESSION['flash_toast_queue']) || !is_array($_SESSION['flash_toast_queue'])) {
    $_SESSION['flash_toast_queue'] = [];
  }
  $_SESSION['flash_toast_queue'][] = ['type' => $type, 'title' => $title, 'message' => $message, 'delay' => $delayMs];
}

// Helper de toasts
function push_toast_logout(string $type, string $title, string $message, int $delayMs = 3000): void {
    if (!isset($_SESSION['flash_toast_queue']) || !is_array($_SESSION['flash_toast_queue'])) {
        $_SESSION['flash_toast_queue'] = [];
    }
    $_SESSION['flash_toast_queue'][] = [
        'type' => $type,
        'title' => $title,
        'message' => $message,
        'delay' => $delayMs
    ];
}




/* =======================
 * Toast helper (fallback)
 * ======================= */
if (!function_exists('push_toast')) {
  function push_toast(string $type, string $title, string $message, int $delayMs = 3000): void {
    if (session_status() === PHP_SESSION_NONE) session_start();
    if (empty($_SESSION['flash_toast_queue']) || !is_array($_SESSION['flash_toast_queue'])) {
      $_SESSION['flash_toast_queue'] = [];
    }
    $_SESSION['flash_toast_queue'][] = [
      'type'=>$type, 'title'=>$title, 'message'=>$message, 'delay'=>$delayMs
    ];
  }
}
/**
 * Retorna [start, end] (string 'Y-m-d') para o período atual e anterior
 */
function dso_period_range($time_grain = 'month', $lookback_n = 1) {
  $now = new DateTime('now');
  $start = clone $now; $end = clone $now;

  switch ($time_grain) {
    case 'day':
      $start->modify('today');
      $end->modify('tomorrow')->modify('-1 second');
      break;
    case 'week':
      $start->modify('monday this week');
      $end->modify('sunday this week 23:59:59');
      break;
    case 'quarter':
      $q = ceil($now->format('n')/3);
      $start = new DateTime($now->format('Y-').sprintf('%02d', ($q-1)*3+1).'-01');
      $end = clone $start; $end->modify('+3 months')->modify('-1 second');
      break;
    case 'year':
      $start = new DateTime($now->format('Y-01-01'));
      $end   = new DateTime($now->format('Y-12-31 23:59:59'));
      break;
    case 'month':
    default:
      $start = new DateTime($now->format('Y-m-01'));
      $end   = clone $start; $end->modify('+1 month')->modify('-1 second');
  }

  // lookback_n > 1 (ex.: últimos 3 meses somados)
  if ((int)$lookback_n > 1) {
    switch ($time_grain) {
      case 'day':     $start->modify('-'.((int)$lookback_n-1).' days'); break;
      case 'week':    $start->modify('-'.((int)$lookback_n-1).' weeks'); break;
      case 'quarter': $start->modify('-'.((int)$lookback_n-1).' quarters'); break;
      case 'year':    $start->modify('-'.((int)$lookback_n-1).' years'); break;
      case 'month':
      default:        $start->modify('-'.((int)$lookback_n-1).' months'); break;
    }
  }

  // período anterior com mesma duração
  $diffDays = (int)$start->diff($end)->format('%a') + 1;
  $prevEnd   = clone $start; $prevEnd->modify('-1 day')->setTime(23,59,59);
  $prevStart = clone $prevEnd; $prevStart->modify('-'.($diffDays-1).' days')->setTime(0,0,0);

  return [
    'current' => ['start'=>$start->format('Y-m-d'), 'end'=>$end->format('Y-m-d')],
    'previous'=> ['start'=>$prevStart->format('Y-m-d'), 'end'=>$prevEnd->format('Y-m-d')]
  ];
}

/** Formata valor conforme o KPI */
function dso_format_value($val, $format='number', $decimals=2, $currency='R$') {
  switch ($format) {
    case 'currency': return $currency.' '.number_format((float)$val, (int)$decimals, ',', '.');
    case 'percent':  return number_format((float)$val, (int)$decimals, ',', '.').'%';
    case 'duration': // assume segundos -> HH:MM
      $mins = floor(((float)$val)/60);
      return sprintf('%02d:%02d', floor($mins/60), $mins%60);
    case 'number':
    default:         return number_format((float)$val, (int)$decimals, ',', '.');
  }
}

/**
 * Busca o valor agregado de uma série (cfg) num período.
 * Usa op_type/agg simplificado (SUM/COUNT/AVG ou DIRECT = SUM(value_expr))
 * where_clause pode ter placeholders que você bind-a em $params
 */
function dso_fetch_agg(PDO $pdo, array $cfg, array $db, $start, $end, array $params = []) {
  // Whitelist de identificadores
  foreach (['table_name','time_column'] as $k) {
    $id = isset($db[$k]) ? $db[$k] : '';
    if (!preg_match('/^[a-zA-Z0-9_]+$/', $id)) {
      throw new RuntimeException("Identificador inválido: {$k}");
    }
  }
  $table = $db['table_name'];
  $date  = $db['time_column'];
  $expr  = $cfg['value_expr'];

  // expr simples (coluna) – se quiser algo complexo, recomendo VIEW
  if (!preg_match('/^[a-zA-Z0-9_.*()+\-\/ ]+$/', $expr)) {
    throw new RuntimeException("value_expr inválida");
  }

  // agregação
  $op = isset($cfg['op_type']) ? $cfg['op_type'] : 'SUM';
  $agg = ($op === 'COUNT') ? 'COUNT(*)' :
         (($op === 'AVG') ? "AVG($expr)" : "SUM($expr)");

  $where = "($date BETWEEN :start AND :end)";
  if (!empty($cfg['where_clause'])) {
    $where .= " AND (".$cfg['where_clause'].")"; // use placeholders!
  }

  $sql = "SELECT $agg AS v FROM `$table` WHERE $where";
  $st = $pdo->prepare($sql);
  $st->bindValue(':start', $start);
  $st->bindValue(':end',   $end);
  foreach ($params as $k=>$v) { $st->bindValue($k,$v); }
  $st->execute();
  $row = $st->fetch(PDO::FETCH_ASSOC);
  return (float)($row && isset($row['v']) ? $row['v'] : 0.0);
}

/** Carrega 1 KPI por ID já calculando valor atual, anterior, delta e % */
function dso_compute_kpi(PDO $pdo, $kpi_id, array $params = []) {
  // carrega KPI + configs
  $k = $pdo->prepare("SELECT * FROM dso_kpis WHERE id=:id AND enabled=1 LIMIT 1");
  $k->execute([':id'=>$kpi_id]);
  $kpi = $k->fetch(PDO::FETCH_ASSOC);
  if (!$kpi) return null;

  $c1 = $pdo->prepare("SELECT cfg.*, db.table_name, db.time_column, db.default_agg
                       FROM dso_db_config cfg
                       JOIN dso_databases db ON db.id = cfg.database_id
                       WHERE cfg.id=:id LIMIT 1");
  $c1->execute([':id'=>$kpi['config_id']]);
  $cfgA = $c1->fetch(PDO::FETCH_ASSOC);
  if (!$cfgA) return null;

  $cfgB = null;
  if (!empty($kpi['ref_config_id'])) {
    $c2 = $pdo->prepare("SELECT cfg.*, db.table_name, db.time_column, db.default_agg
                         FROM dso_db_config cfg
                         JOIN dso_databases db ON db.id = cfg.database_id
                         WHERE cfg.id=:id LIMIT 1");
    $c2->execute([':id'=>$kpi['ref_config_id']]);
    $cfgB = $c2->fetch(PDO::FETCH_ASSOC);
  }

  $period = dso_period_range($kpi['time_grain'], (int)$kpi['lookback_n']);
  $curA = dso_fetch_agg($pdo, $cfgA, $cfgA, $period['current']['start'], $period['current']['end'], $params);
  $prevA= dso_fetch_agg($pdo, $cfgA, $cfgA, $period['previous']['start'], $period['previous']['end'], $params);

  $cur = $curA; $prev = $prevA;

  switch ($kpi['op_type']) {
    case 'DIFF':
      if ($cfgB) {
        $curB  = dso_fetch_agg($pdo, $cfgB, $cfgB, $period['current']['start'], $period['current']['end'], $params);
        $prevB = dso_fetch_agg($pdo, $cfgB, $cfgB, $period['previous']['start'], $period['previous']['end'], $params);
        $cur  = $curA - $curB;
        $prev = $prevA - $prevB;
      }
      break;
    case 'RATIO':
      if ($cfgB) {
        $curB  = max(1e-9, dso_fetch_agg($pdo, $cfgB, $cfgB, $period['current']['start'], $period['current']['end'], $params));
        $prevB = max(1e-9, dso_fetch_agg($pdo, $cfgB, $cfgB, $period['previous']['start'], $period['previous']['end'], $params));
        $cur  = $curA / $curB;
        $prev = $prevA / $prevB;
      }
      break;
    case 'DIRECT':
    case 'SUM':
    case 'COUNT':
    case 'AVG':
    default:
      // já está em $cur/$prev
      break;
  }

  $delta     = $cur - $prev;
  $delta_pct = ($prev == 0) ? 0 : ($delta / $prev) * 100.0;

  // meta & tendência
  $goodDir = $kpi['good_dir'] === 'down' ? 'down' : 'up';
  $hitGoal = null;
  if ($kpi['target_value'] !== null) {
    $hitGoal = ($goodDir === 'up') ? ($cur >= (float)$kpi['target_value'])
                                   : ($cur <= (float)$kpi['target_value']);
  }

  return [
    'id'         => (int)$kpi_id,
    'title'      => $kpi['title'],
    'current'    => $cur,
    'previous'   => $prev,
    'delta'      => $delta,
    'delta_pct'  => $delta_pct,
    'format'     => $kpi['format'],
    'currency'   => $kpi['currency_symbol'],
    'decimals'   => (int)$kpi['decimals'],
    'good_dir'   => $goodDir,
    'target'     => ($kpi['target_value'] !== null) ? (float)$kpi['target_value'] : null,
    'hit_goal'   => $hitGoal,
    'icon'       => $kpi['icon'],
    'color'      => $kpi['color'],
  ];
}








/* =======================
 * CSRF helpers
 * ======================= */
function dso_csrf_ensure(string $key = 'csrf_pref'): string {
  if (session_status() === PHP_SESSION_NONE) session_start();
  if (empty($_SESSION[$key])) {
    $_SESSION[$key] = bin2hex(random_bytes(16));
  }
  return $_SESSION[$key];
}

function dso_csrf_check(string $token, string $key = 'csrf_pref'): bool {
  if (session_status() === PHP_SESSION_NONE) session_start();
  return isset($_SESSION[$key]) && hash_equals($_SESSION[$key], (string)$token);
}






/* =======================
 * dso_apikey_ia — READ (apikey + model)
 * ======================= */
function dso_get_ia_config(PDO $pdo): array {
  $sql = "SELECT apikey, model FROM dso_apikey_ia ORDER BY id DESC LIMIT 1";
  $row = $pdo->query($sql)->fetch(PDO::FETCH_ASSOC);

  if (!$row) {
    return [
      'apikey' => '',
      'model'  => '',
    ];
  }

  return [
    'apikey' => (string)($row['apikey'] ?? ''),
    'model'  => (string)($row['model'] ?? ''),
  ];
}

/* Se quiser só a API key (compatível com o que já usa) */
function dso_get_apikey(PDO $pdo): string {
  $cfg = dso_get_ia_config($pdo);
  return $cfg['apikey'];
}

/* Se quiser só o model atual */
function dso_get_ia_model(PDO $pdo): string {
  $cfg = dso_get_ia_config($pdo);
  return $cfg['model'];
}

function dso_get_apikey_masked(PDO $pdo): string {
  $raw = dso_get_apikey($pdo);
  if ($raw === '') return '';
  return '************' . substr($raw, -4);
}
/* =======================
 * dso_apikey_ia — UPSERT (apikey + model)
 * ======================= */
function dso_set_apikey(PDO $pdo, ?string $apikey, ?string $model): bool {
  $apikey = trim((string)$apikey);
  $model  = trim((string)$model);

  // Se não veio nada, não faz nada
  if ($apikey === '' && $model === '') {
    return false;
  }

  $pdo->beginTransaction();
  try {
    $row = $pdo->query("SELECT id, apikey, model FROM dso_apikey_ia ORDER BY id DESC LIMIT 1")->fetch(PDO::FETCH_ASSOC);

    if ($row) {
      // Mantém o que já existe se o campo veio vazio
      $newKey   = ($apikey === '' ? (string)$row['apikey'] : $apikey);
      $newModel = ($model  === '' ? (string)($row['model'] ?? 'gpt-4.1-mini') : $model);

      $st = $pdo->prepare("UPDATE dso_apikey_ia 
                           SET apikey = :k, model = :m, reg_date = NOW()
                           WHERE id = :id");
      $st->execute([
        ':k'  => $newKey,
        ':m'  => $newModel,
        ':id' => $row['id']
      ]);
    } else {
      // Primeira configuração: exige pelo menos apikey
      if ($apikey === '') {
        throw new RuntimeException('API key não pode ser vazia na primeira configuração.');
      }
      $insertModel = ($model === '' ? 'gpt-4.1-mini' : $model);
      $st = $pdo->prepare("INSERT INTO dso_apikey_ia (apikey, model, reg_date)
                           VALUES (:k, :m, NOW())");
      $st->execute([
        ':k' => $apikey,
        ':m' => $insertModel
      ]);
    }

    $pdo->commit();
    return true;
  } catch (Throwable $e) {
    $pdo->rollBack();
    throw $e;
  }
}
function dso_handle_post_apikey(PDO $pdo, array $post, $lang = null): void {

  $fala = get_current_language($pdo);
  push_toast('info', $fala->get('config_info'), $fala->get('acessando_sistema'), 2500);

  if (!isset($post['_csrf']) || !dso_csrf_check($post['_csrf'])) {
    push_toast('danger', $fala->get('erro'), $fala->get('csrf_invalido'), 3500);
    dso_redirect('/SmartOfficeHybrid/admin/preferences.php', 4500, 'Aguarde...', 'Processando...');
  }

  $key   = isset($post['apikey']) ? (string)$post['apikey'] : '';
  $model = isset($post['model'])  ? (string)$post['model']  : '';

  try {
    if (trim($key) === '' && trim($model) === '') {
      // Nada foi alterado
      push_toast('info', $fala->get('info'), $fala->get('api_vazia_ignorada'), 2500);
    } else {
      dso_set_apikey($pdo, $key, $model);
      push_toast('success', $fala->get('sucesso'), $fala->get('config_api_salva_ok'), 2500);
    }
  } catch (Throwable $e) {
    push_toast('danger', $fala->get('erro'), $e->getMessage(), 4000);
  }

  dso_redirect('/SmartOfficeHybrid/admin/preferences.php', 4500, 'Aguarde...', 'Processando...');
}


/* =======================
 * dso_apikey_ia — READ
 * ======================= * /
function dso_get_apikey(PDO $pdo): string {
  $sql = "SELECT apikey FROM dso_apikey_ia ORDER BY id DESC LIMIT 1";
  $row = $pdo->query($sql)->fetch(PDO::FETCH_ASSOC);
  return $row ? (string)$row['apikey'] : '';
}

function dso_get_apikey_masked(PDO $pdo): string {
  $raw = dso_get_apikey($pdo);
  if ($raw === '') return '';
  return '************' . substr($raw, -4);
}

/* =======================
 * dso_apikey_ia — UPSERT (update última ou insert)
 * ======================= * /
function dso_set_apikey(PDO $pdo, string $apikey): bool {
  $apikey = trim($apikey);
  if ($apikey === '') return false;

  $pdo->beginTransaction();
  try {
    $row = $pdo->query("SELECT id FROM dso_apikey_ia ORDER BY id DESC LIMIT 1")->fetch(PDO::FETCH_ASSOC);
    if ($row) {
      $st = $pdo->prepare("UPDATE dso_apikey_ia SET apikey=:k, reg_date=NOW() WHERE id=:id");
      $st->execute([':k'=>$apikey, ':id'=>$row['id']]);
    } else {
      $st = $pdo->prepare("INSERT INTO dso_apikey_ia (apikey, reg_date) VALUES (:k, NOW())");
      $st->execute([':k'=>$apikey]);
    }
    $pdo->commit();
    return true;
  } catch (Throwable $e) {
    $pdo->rollBack();
    throw $e;
  }
}


/* =========================================
 * Handler único para o POST do formulário
 * ========================================= * /
function dso_handle_post_apikey(PDO $pdo, array $post, $lang = null): void {

  $fala = get_current_language($pdo);
  push_toast('info', $fala->get('config_info'), $fala->get('acessando_sistema'), 2500);


  if (!isset($post['_csrf']) || !dso_csrf_check($post['_csrf'])) {
    push_toast('danger', $fala->get('erro'), $fala->get('csrf_invalido'), 3500);
    //header('Location: /SmartOfficeHybrid/admin/preferences.php'); exit;
    dso_redirect('/SmartOfficeHybrid/admin/preferences.php', 4500, 'Aguarde...', 'Processando...');
  }

  $key = isset($post['apikey']) ? (string)$post['apikey'] : '';

  try {
    if (trim($key) === '') {
      push_toast('info', $fala->get('info'), $fala->get('api_vazia_ignorada'), 2500);
    } else {
      dso_set_apikey($pdo, $key);
      push_toast('success', $fala->get('sucesso'), $fala->get('config_api_salva_ok'), 2500);
    }
  } catch (Throwable $e) {
    push_toast('danger', $fala->get('erro'), $e->getMessage(), 4000);
  }

  //header('Location: /SmartOfficeHybrid/admin/preferences.php'); exit;
  dso_redirect('/SmartOfficeHybrid/admin/preferences.php', 4500, 'Aguarde...', 'Processando...');
}
/** */






/* ============================
 * dso_settings — schema ensure
 * ============================ 
*/
function dso_settings_ensure_schema(PDO $pdo): void {

  // garante colunas (MySQL 5.7/MariaDB 10.3 não têm ADD COLUMN IF NOT EXISTS)
  // checa information_schema e cria se faltar
  $cols = $pdo->prepare("
    SELECT COLUMN_NAME FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'dso_settings'
  ");
  $cols->execute();
  $have = array_flip(array_map('strtolower', array_column($cols->fetchAll(PDO::FETCH_ASSOC),'COLUMN_NAME')));

  if (!isset($have['public_home'])) {
    $pdo->exec("ALTER TABLE dso_settings ADD COLUMN public_home TINYINT(1) NOT NULL DEFAULT 1");
  }
  if (!isset($have['language'])) {
    $pdo->exec("ALTER TABLE dso_settings ADD COLUMN language VARCHAR(10) NOT NULL DEFAULT 'pt_BR'");
  }
  if (!isset($have['updated_at'])) {
    $pdo->exec("ALTER TABLE dso_settings ADD COLUMN updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP");
  }

  // garante linha única id=1
  $pdo->exec("INSERT INTO dso_settings (id, public_home, language) VALUES (1, 1, 'pt_BR')
              ON DUPLICATE KEY UPDATE id = 1");
}

/* ============================
 * dso_settings — getters/setters
 * ============================ */
function dso_get_settings(PDO $pdo): array {
  dso_settings_ensure_schema($pdo);
  $st = $pdo->query("SELECT id, public_home, language, updated_at FROM dso_settings WHERE id=1 LIMIT 1");
  $row = $st->fetch(PDO::FETCH_ASSOC);
  if (!$row) return ['id'=>1,'public_home'=>1,'language'=>'pt_BR','updated_at'=>null];
  // normaliza tipos
  $row['public_home'] = (int)$row['public_home'];
  $row['language']    = (string)$row['language'];
  return $row;
}

// Back-compat para quem já usa get_dso_settings($pdo)
if (!function_exists('get_dso_settings')) {
  function get_dso_settings(PDO $pdo): array { return dso_get_settings($pdo); }
}

function dso_set_settings(PDO $pdo, $public_home, $language): bool {
  dso_settings_ensure_schema($pdo);

  $ph = (int)((string)$public_home === '1' ? 1 : 0);
  $langsAllowed = ['pt_BR','en_US','es_ES'];
  $lg = in_array($language, $langsAllowed, true) ? $language : 'pt_BR';

  $st = $pdo->prepare("
    INSERT INTO dso_settings (id, public_home, language, updated_at)
    VALUES (1, :ph, :lg, NOW())
    ON DUPLICATE KEY UPDATE public_home=VALUES(public_home), language=VALUES(language), updated_at=NOW()
  ");
  return $st->execute([':ph'=>$ph, ':lg'=>$lg]);
}

/* =========================================
 * Handler de POST do formulário de settings
 * ========================================= */
function dso_handle_post_settings(PDO $pdo, array $post, $lang = null): void {
  
  $fala = get_current_language($pdo);
  push_toast('info', $fala->get('config_info'), $fala->get('acessando_sistema'), 2500);

  // CSRF
  if (!isset($post['_csrf']) || !dso_csrf_check($post['_csrf'], 'csrf_pref')) {
    push_toast('danger', $fala->get('config_erro'), $fala->get('config_csrf_invalido'), 3500);
    //header('Location: /SmartOfficeHybrid/admin/preferences.php'); exit;
    dso_redirect('/SmartOfficeHybrid/admin/preferences.php', 4500, 'Aguarde...', 'Processando...');
  }

  $public_home = $post['public_home'] ?? '1';
  $language    = $post['language']    ?? 'pt_BR';

  try {
    dso_set_settings($pdo, $public_home, $language);
    push_toast('success', $fala->get('config_sucesso'), $fala->get('config_salva_ok'), 2500);
  } catch (Throwable $e) {
    push_toast('danger', $fala->get('config_erro'), $e->getMessage(), 4000);
  }

  //header('Location: /SmartOfficeHybrid/admin/preferences.php'); exit;
  dso_redirect('/SmartOfficeHybrid/admin/preferences.php', 4500, 'Aguarde...', 'Processando...');
}






/* =======================
 * CSRF do login (reuso)
 * ======================= */
function dso_csrf_login_token(): string {
  return dso_csrf_ensure('csrf_login');
}
function dso_csrf_login_check(string $token): bool {
  return dso_csrf_check($token, 'csrf_login');
}

/* =====================================================
 * Handler de LOGIN: processa POST e redireciona
 * Requer: find_user_by_username(), authenticate(), ensure_users_admin_table()
 * ===================================================== */
function dso_handle_post_login(PDO $pdo, array $post, $lang = null): void {

  $fala = get_current_language($pdo);
  push_toast('info', $fala->get('config_info'), $fala->get('acessando_sistema'), 2500);

  // CSRF
  if (!isset($post['_csrf']) || !dso_csrf_login_check((string)$post['_csrf'])) {
    push_toast('danger', $fala->get('config_erro'), $fala->get('config_csrf_invalido'), 3500);
    //header('Location: /SmartOfficeHybrid/admin/login.php'); exit;
    dso_redirect('/SmartOfficeHybrid/admin/login.php', 4500, 'Aguarde...', 'Processando...');
  }

  // Garantir tabela
  ensure_users_admin_table($pdo);

  // Nome de usuário: aceita vários names
  $username = trim((string)(
    $post['username'] ?? $post['user'] ?? $post['email'] ?? ''
  ));

  // Senha: aceita vários names
  $password = (string)(
    $post['password'] ?? $post['pass'] ?? $post['senha'] ?? ''
  );

  // Toast "info" antes — 1.5s
  push_toast('info',
    $fala->get('acessando_sistema'),
    $fala->get('validando_credenciais'),
    1500
  );

  // Campos vazios
  if ($username === '' || $password === '') {
    push_toast('danger', $fala->get('falha_login'), $fala->get('preencha_usuario_senha'), 3000);
    //header('Location: /SmartOfficeHybrid/admin/login.php'); exit;
    dso_redirect('/SmartOfficeHybrid/admin/login.php', 4500, 'Aguarde...', 'Processando...');
  }

  // Busca usuário
  $user = find_user_by_username($username, $pdo);
  if (!$user) {
    push_toast('danger', $fala->get('falha_login'), $fala->get('usuario_nao_existe'), 1000);
    //header('Location: /SmartOfficeHybrid/admin/login.php'); exit;
    dso_redirect('/SmartOfficeHybrid/admin/login.php', 4500, 'Aguarde...', 'Processando...');
  }

  // Verifica senha (atalho rápido antes de chamar authenticate)
  if (!password_verify($password, $user['password_hash'])) {
    push_toast('danger', $fala->get('falha_login'), $fala->get('senha_invalida'), 1000);
    //header('Location: /SmartOfficeHybrid/admin/login.php'); exit;
    dso_redirect('/SmartOfficeHybrid/admin/login.php', 4500, 'Aguarde...', 'Processando...');
  }

  // Autentica (permite você plugar regras extras, logs, etc.)
  $auth = authenticate($username, $password, $pdo);
  if (!empty($auth['ok'])) {
    if (session_status() === PHP_SESSION_NONE) session_start();
    $_SESSION['user_id']  = (int)$auth['user']['id'];
    $_SESSION['username'] = (string)$auth['user']['username'];

    // Sucesso — 3s
    push_toast('success',
      $fala->get('config_sucesso'),
      $fala->get('login_sucesso'),
      1000
    );
    push_toast('success',
      $fala->get('config_sucesso'),
      $fala->get('bem_vindo'),
      2000
    );

    //header('Location: /SmartOfficeHybrid/admin/login_redirect.php'); exit;
    dso_redirect('/SmartOfficeHybrid/admin/dashboard.php', 6000, 'Aguarde...', 'Processando...');
  }

  // Fallback
  push_toast('danger', $fala->get('falha_login'), $fala->get('credenciais_invalidas'), 3000);
  header('Location: /SmartOfficeHybrid/admin/login.php'); exit;
}

/* =========================================
 * Guard: se já logado, redireciona ao dashboard
 * (para usar no login.php)
 * ========================================= */
function redirect_if_logged(): void {
  static $done = false;
  if ($done) return;
  $done = true;

  if (session_status() === PHP_SESSION_NONE) session_start();
  if (!empty($_SESSION['user_id'])) {
    //header('Location: /SmartOfficeHybrid/admin/dashboard.php'); exit;
    dso_redirect('/SmartOfficeHybrid/admin/dashboard.php', 4500, 'Aguarde...', 'Processando...');
  }
}
































/**
 * Carrega todos os modelos de gráfico ativos para o dashboard,
 * já trazendo as infos da métrica (dso_grafico).
 */
function dso_get_dashboard_models(PDO $pdo): array
{
    $sql = "
        SELECT 
            m.*, 
            g.tabela,
            g.coluna_grupo,
            g.coluna_valor,
            g.agregacao,
            g.filtro_where
        FROM dso_model_grafico AS m
        INNER JOIN dso_grafico AS g ON g.id = m.grafico_id
        WHERE m.ativo = 1
          AND g.ativo = 1
        ORDER BY m.ordem, m.id
    ";

    $stmt = $pdo->query($sql);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * Pequena ajudante para validar nomes de tabela/coluna
 * (somente letras, números, _ e . )
 */
function dso_is_safe_identifier(string $ident): bool
{
    return (bool) preg_match('/^[a-zA-Z0-9_\.]+$/', $ident);
}

/**
 * Executa a métrica de um modelo e devolve dados prontos para o ApexCharts.
 *
 * $model precisa ter os campos de dso_model_grafico + (tabela, coluna_grupo, coluna_valor, agregacao, filtro_where)
 * que vem de dso_get_dashboard_models().
 */
function dso_fetch_grafico_data(PDO $pdo, array $model): array
{
    // Tabela principal
    $table = $model['tabela'];
    if (!dso_is_safe_identifier($table)) {
        throw new RuntimeException("Nome de tabela inválido: {$table}");
    }

    // Colunas de agrupamento (podem ser várias, separadas por vírgula)
    $groupCols = [];
    if (!empty($model['coluna_grupo'])) {
        foreach (explode(',', $model['coluna_grupo']) as $col) {
            $col = trim($col);
            if ($col === '') {
                continue;
            }
            if (!dso_is_safe_identifier($col)) {
                throw new RuntimeException("Coluna de grupo inválida: {$col}");
            }
            $groupCols[] = $col;
        }
    }

    // Coluna de valor
    $valueExpr = trim((string) ($model['coluna_valor'] ?? ''));
    if ($valueExpr === '') {
        $valueExpr = '*';
    } elseif (!dso_is_safe_identifier($valueExpr)) {
        throw new RuntimeException("Coluna de valor inválida: {$valueExpr}");
    }

    // Agregação
    $agg = strtoupper($model['agregacao'] ?? 'COUNT');
    if (!in_array($agg, ['COUNT', 'SUM', 'AVG', 'MAX', 'MIN'], true)) {
        $agg = 'COUNT';
    }

    if ($agg === 'COUNT' && $valueExpr === '*') {
        $aggSql = "COUNT(*)";
    } else {
        $aggSql = "{$agg}({$valueExpr})";
    }

    // FROM + JOINs
    $fromSql = "`{$table}` AS t";

    $stmtJoin = $pdo->prepare("
        SELECT *
        FROM dso_grafico_join
        WHERE grafico_id = :gid AND ativo = 1
        ORDER BY id
    ");
    $stmtJoin->execute([':gid' => $model['grafico_id']]);
    $joins = $stmtJoin->fetchAll(PDO::FETCH_ASSOC);

    foreach ($joins as $j) {
        $jt   = $j['tabela'];
        $alias = $j['alias'];
        $jtype = strtoupper($j['tipo_join']); // INNER, LEFT, RIGHT
        $on    = trim($j['on_expr']);

        if (!dso_is_safe_identifier($jt) || !dso_is_safe_identifier($alias)) {
            throw new RuntimeException("JOIN inválido em dso_grafico_join id={$j['id']}");
        }

        // on_expr aqui é texto livre – é painel admin, então só cuide ao preencher no sistema.
        $fromSql .= " {$jtype} JOIN `{$jt}` AS {$alias} ON {$on}";
    }

    // SELECT
    $select = [];
    foreach ($groupCols as $gc) {
        $select[] = $gc;
    }
    $select[] = "{$aggSql} AS v";

    $sql = "SELECT " . implode(', ', $select) . " FROM {$fromSql}";

    // WHERE opcional vindo de dso_grafico.filtro_where
    $whereClauses = [];
    if (!empty($model['filtro_where'])) {
        $whereClauses[] = '(' . $model['filtro_where'] . ')';
    }
    if ($whereClauses) {
        $sql .= " WHERE " . implode(' AND ', $whereClauses);
    }

    // GROUP BY se tiver coluna_grupo
    if ($groupCols) {
        $sql .= " GROUP BY " . implode(', ', $groupCols);
    }

    $stmt = $pdo->prepare($sql);
    $stmt->execute();
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Monta labels/values
    $labels = [];
    $values = [];

    foreach ($rows as $row) {
        if ($groupCols) {
            $parts = [];
            foreach ($groupCols as $gc) {
                $parts[] = isset($row[$gc]) ? (string) $row[$gc] : '';
            }
            $labels[] = implode(' / ', $parts);
        } else {
            // Se não tiver grupo, label vira o nome da métrica
            $labels[] = $model['nome'];
        }

        $values[] = isset($row['v']) ? (float) $row['v'] : 0.0;
    }

    return [
        'labels' => $labels,
        'series' => [
            [
                'name' => $model['nome'],
                'data' => $values,
            ],
        ],
    ];
}

























/**
 * Redireciona sempre para a tela de configuração de métricas/gráficos.
 */
function dso_redirect_novagrafico(): void
{
  dso_redirect('/SmartOfficeHybrid/admin/novagrafico.php', 4500, 'Aguarde...', 'Processando...');
  //header('Location: /SmartOfficeHybrid/admin/novagrafico.php');
  //exit;
}

/**
 * Trata criação/edição de métrica (dso_grafico).
 * Usa $_POST diretamente.
 */
function dso_handle_grafico_save(PDO $pdo): void
{
    if (!isset($_POST['_csrf']) || !dso_csrf_check($_POST['_csrf'], 'csrf_grafico')) {
        push_toast('danger', 'Erro', 'Token CSRF inválido. Atualize a página e tente novamente.', 3500);
        dso_redirect_novagrafico();
    }

    $id          = (int) ($_POST['id'] ?? 0); // 0 = novo, >0 = edição
    $nome        = trim((string) ($_POST['nome'] ?? ''));
    $descricao   = trim((string) ($_POST['descricao'] ?? ''));
    $tabela      = trim((string) ($_POST['tabela'] ?? ''));
    $agregacao   = (string) ($_POST['agregacao'] ?? 'COUNT');
    $filtro      = trim((string) ($_POST['filtro_where'] ?? ''));
    $gruposArray = $_POST['colunas_grupo'] ?? [];
    $colunaValor = trim((string) ($_POST['coluna_valor'] ?? ''));

    if ($nome === '' || $tabela === '') {
        push_toast('danger', 'Campos obrigatórios', 'Preencha pelo menos Nome e Tabela.', 3500);
        dso_redirect_novagrafico();
    }

    if (!preg_match('/^[A-Za-z0-9_]+$/', $tabela)) {
        push_toast('danger', 'Tabela inválida', 'Nome de tabela contém caracteres não permitidos.', 3500);
        dso_redirect_novagrafico();
    }

    // Colunas de agrupamento
    $coluna_grupo = null;
    if (!empty($gruposArray) && is_array($gruposArray)) {
        $limpas = [];
        foreach ($gruposArray as $g) {
            $g = trim((string) $g);
            if ($g === '') {
                continue;
            }
            if (!preg_match('/^[A-Za-z0-9_]+$/', $g)) {
                push_toast('danger', 'Coluna inválida', 'Uma das colunas de agrupamento é inválida.', 3500);
                dso_redirect_novagrafico();
            }
            $limpas[] = $g;
        }
        if ($limpas) {
            $coluna_grupo = implode(',', $limpas);
        }
    }

    // Coluna de valor
    $coluna_valor = null;
    if ($colunaValor !== '') {
        if (!preg_match('/^[A-Za-z0-9_ ]+$/', $colunaValor)) {
            push_toast('danger', 'Expressão inválida', 'Coluna de valor contém caracteres não permitidos.', 3500);
            dso_redirect_novagrafico();
        }
        $coluna_valor = $colunaValor;
    }

    $filtro_where = $filtro !== '' ? $filtro : null;

    try {
        if ($id > 0) {
            // UPDATE
            $sql = "
                UPDATE dso_grafico
                   SET nome         = :nome,
                       descricao    = :descricao,
                       tabela       = :tabela,
                       coluna_grupo = :coluna_grupo,
                       coluna_valor = :coluna_valor,
                       agregacao    = :agregacao,
                       filtro_where = :filtro_where
                 WHERE id = :id
            ";
            $st = $pdo->prepare($sql);
            $st->execute([
                ':id'            => $id,
                ':nome'          => $nome,
                ':descricao'     => $descricao !== '' ? $descricao : null,
                ':tabela'        => $tabela,
                ':coluna_grupo'  => $coluna_grupo,
                ':coluna_valor'  => $coluna_valor,
                ':agregacao'     => $agregacao,
                ':filtro_where'  => $filtro_where,
            ]);
            push_toast('success', 'Métrica atualizada', 'Configuração de métrica atualizada com sucesso.', 3000);
        } else {
            // INSERT
            $sql = "
                INSERT INTO dso_grafico
                (nome, descricao, tabela, coluna_grupo, coluna_valor, agregacao, filtro_where, ativo)
                VALUES
                (:nome, :descricao, :tabela, :coluna_grupo, :coluna_valor, :agregacao, :filtro_where, 1)
            ";
            $st = $pdo->prepare($sql);
            $st->execute([
                ':nome'          => $nome,
                ':descricao'     => $descricao !== '' ? $descricao : null,
                ':tabela'        => $tabela,
                ':coluna_grupo'  => $coluna_grupo,
                ':coluna_valor'  => $coluna_valor,
                ':agregacao'     => $agregacao,
                ':filtro_where'  => $filtro_where,
            ]);
            push_toast('success', 'Métrica criada', 'Configuração de métrica salva com sucesso.', 3000);
        }
    } catch (Throwable $e) {
        push_toast('danger', 'Erro ao salvar', $e->getMessage(), 4000);
    }

    dso_redirect_novagrafico();
}

/**
 * Exclui métrica (dso_grafico).
 */
function dso_handle_grafico_delete(PDO $pdo): void
{
    if (!isset($_POST['_csrf']) || !dso_csrf_check($_POST['_csrf'], 'csrf_grafico')) {
        push_toast('danger', 'Erro', 'Token CSRF inválido.', 3500);
        dso_redirect_novagrafico();
    }

    $id = (int) ($_POST['id'] ?? 0);
    if ($id > 0) {
        try {
            $st = $pdo->prepare("DELETE FROM dso_grafico WHERE id = :id");
            $st->execute([':id' => $id]);
            // modelos/join devem estar em CASCADE; se não, vai dar erro aqui
            push_toast('success', 'Métrica removida', 'Métrica e modelos associados foram removidos.', 3000);
        } catch (Throwable $e) {
            push_toast('danger', 'Erro ao excluir métrica', $e->getMessage(), 4000);
        }
    }

    dso_redirect_novagrafico();
}

/**
 * Cria/edita JOIN (dso_grafico_join).
 */
function dso_handle_join_save(PDO $pdo): void
{
    if (!isset($_POST['_csrf']) || !dso_csrf_check($_POST['_csrf'], 'csrf_join')) {
        push_toast('danger', 'Erro', 'Token CSRF inválido.', 3500);
        dso_redirect_novagrafico();
    }

    $id         = (int) ($_POST['id'] ?? 0);
    $grafico_id = (int) ($_POST['grafico_id'] ?? 0);
    $tabela     = trim((string) ($_POST['tabela_join'] ?? ''));
    $alias      = trim((string) ($_POST['alias'] ?? ''));
    $tipo_join  = (string) ($_POST['tipo_join'] ?? 'INNER');
    $on_expr    = trim((string) ($_POST['on_expr'] ?? ''));

    if ($grafico_id <= 0 || $tabela === '' || $alias === '' || $on_expr === '') {
        push_toast('danger', 'Campos obrigatórios', 'Preencha todos os campos do JOIN.', 3500);
        dso_redirect_novagrafico();
    }

    if (!preg_match('/^[A-Za-z0-9_]+$/', $tabela)) {
        push_toast('danger', 'Tabela inválida', 'Nome de tabela de JOIN inválido.', 3500);
        dso_redirect_novagrafico();
    }
    if (!preg_match('/^[A-Za-z0-9_]+$/', $alias)) {
        push_toast('danger', 'Alias inválido', 'Alias deve conter apenas letras, números e _.', 3500);
        dso_redirect_novagrafico();
    }

    $tipo_ok = ['INNER', 'LEFT', 'RIGHT'];
    if (!in_array($tipo_join, $tipo_ok, true)) {
        $tipo_join = 'INNER';
    }

    try {
        if ($id > 0) {
            $sql = "
                UPDATE dso_grafico_join
                   SET grafico_id = :grafico_id,
                       tabela     = :tabela,
                       alias      = :alias,
                       tipo_join  = :tipo_join,
                       on_expr    = :on_expr
                 WHERE id = :id
            ";
            $st = $pdo->prepare($sql);
            $st->execute([
                ':id'         => $id,
                ':grafico_id' => $grafico_id,
                ':tabela'     => $tabela,
                ':alias'      => $alias,
                ':tipo_join'  => $tipo_join,
                ':on_expr'    => $on_expr,
            ]);
            push_toast('success', 'JOIN atualizado', 'JOIN atualizado com sucesso.', 3000);
        } else {
            $sql = "
                INSERT INTO dso_grafico_join
                (grafico_id, tabela, alias, tipo_join, on_expr, ativo)
                VALUES
                (:grafico_id, :tabela, :alias, :tipo_join, :on_expr, 1)
            ";
            $st = $pdo->prepare($sql);
            $st->execute([
                ':grafico_id' => $grafico_id,
                ':tabela'     => $tabela,
                ':alias'      => $alias,
                ':tipo_join'  => $tipo_join,
                ':on_expr'    => $on_expr,
            ]);
            push_toast('success', 'JOIN criado', 'JOIN adicionado à métrica.', 3000);
        }
    } catch (Throwable $e) {
        push_toast('danger', 'Erro ao salvar JOIN', $e->getMessage(), 4000);
    }

    dso_redirect_novagrafico();
}

/**
 * Exclui JOIN.
 */
function dso_handle_join_delete(PDO $pdo): void
{
    if (!isset($_POST['_csrf']) || !dso_csrf_check($_POST['_csrf'], 'csrf_join')) {
        push_toast('danger', 'Erro', 'Token CSRF inválido.', 3500);
        dso_redirect_novagrafico();
    }

    $id = (int) ($_POST['id'] ?? 0);
    if ($id > 0) {
        try {
            $st = $pdo->prepare("DELETE FROM dso_grafico_join WHERE id = :id");
            $st->execute([':id' => $id]);
            push_toast('success', 'JOIN removido', 'JOIN removido com sucesso.', 3000);
        } catch (Throwable $e) {
            push_toast('danger', 'Erro ao excluir JOIN', $e->getMessage(), 4000);
        }
    }

    dso_redirect_novagrafico();
}

/**
 * Cria/edita modelo de exibição (dso_model_grafico).
 * Agora suporta UPDATE quando vier id > 0.
 */
function dso_handle_model_save(PDO $pdo): void
{
    if (!isset($_POST['_csrf']) || !dso_csrf_check($_POST['_csrf'], 'csrf_model')) {
        push_toast('danger', 'Erro', 'Token CSRF inválido.', 3500);
        dso_redirect_novagrafico();
    }

    $id              = (int) ($_POST['id'] ?? 0);
    $grafico_id      = (int) ($_POST['grafico_id'] ?? 0);
    $nome_model      = trim((string) ($_POST['nome_model'] ?? ''));
    $descricao_model = trim((string) ($_POST['descricao_model'] ?? ''));
    $icone           = trim((string) ($_POST['icone'] ?? ''));
    $info            = (string) ($_POST['info'] ?? 'INFO');
    $tipo            = (string) ($_POST['tipo'] ?? 'BARRA');
    $exibicao        = (string) ($_POST['exibicao'] ?? 'CARD');
    $cor_principal   = trim((string) ($_POST['cor_principal'] ?? ''));
    $cor_secundaria  = trim((string) ($_POST['cor_secundaria'] ?? ''));
    $ordem           = (int) ($_POST['ordem'] ?? 1);

    if ($grafico_id <= 0 || $nome_model === '') {
        push_toast('danger', 'Campos obrigatórios', 'Selecione a métrica e informe o nome do modelo.', 3500);
        dso_redirect_novagrafico();
    }

    $tipos_ok = ['KPI-1', 'KPI-2', 'BARRA', 'LINHA', 'DONUT', 'AREA'];
    if (!in_array($tipo, $tipos_ok, true)) {
        $tipo = 'BARRA';
    }

    $infos_ok = ['INFO', 'ALERT', 'SUCCESS', 'DANGER'];
    if (!in_array($info, $infos_ok, true)) {
        $info = 'INFO';
    }

    $exib_ok = ['CARD', 'FULL', 'GRANDE', 'MEDIO', 'PEQUENO', 'MINI', 'MICRO'];
    if (!in_array($exibicao, $exib_ok, true)) {
        $exibicao = 'CARD';
    }

    if ($ordem <= 0) {
        $ordem = 1;
    }

    try {
        if ($id > 0) {
            // UPDATE
            $sql = "
                UPDATE dso_model_grafico
                   SET nome           = :nome,
                       descricao      = :descricao,
                       grafico_id     = :grafico_id,
                       icone          = :icone,
                       info           = :info,
                       tipo           = :tipo,
                       cor_principal  = :cor_principal,
                       cor_secundaria = :cor_secundaria,
                       exibicao       = :exibicao,
                       ordem          = :ordem
                 WHERE id = :id
            ";
            $st = $pdo->prepare($sql);
            $st->execute([
                ':id'            => $id,
                ':nome'          => $nome_model,
                ':descricao'     => $descricao_model !== '' ? $descricao_model : null,
                ':grafico_id'    => $grafico_id,
                ':icone'         => $icone !== '' ? $icone : null,
                ':info'          => $info,
                ':tipo'          => $tipo,
                ':cor_principal' => $cor_principal !== '' ? $cor_principal : null,
                ':cor_secundaria'=> $cor_secundaria !== '' ? $cor_secundaria : null,
                ':exibicao'      => $exibicao,
                ':ordem'         => $ordem,
            ]);
            push_toast('success', 'Modelo atualizado', 'Modelo de gráfico/KPI atualizado com sucesso.', 3000);
        } else {
            // INSERT
            $sql = "
                INSERT INTO dso_model_grafico
                (nome, descricao, grafico_id, icone, info, tipo,
                 cor_principal, cor_secundaria, exibicao, ordem, ativo)
                VALUES
                (:nome, :descricao, :grafico_id, :icone, :info, :tipo,
                 :cor_principal, :cor_secundaria, :exibicao, :ordem, 1)
            ";
            $st = $pdo->prepare($sql);
            $st->execute([
                ':nome'          => $nome_model,
                ':descricao'     => $descricao_model !== '' ? $descricao_model : null,
                ':grafico_id'    => $grafico_id,
                ':icone'         => $icone !== '' ? $icone : null,
                ':info'          => $info,
                ':tipo'          => $tipo,
                ':cor_principal' => $cor_principal !== '' ? $cor_principal : null,
                ':cor_secundaria'=> $cor_secundaria !== '' ? $cor_secundaria : null,
                ':exibicao'      => $exibicao,
                ':ordem'         => $ordem,
            ]);
            push_toast('success', 'Modelo criado', 'Modelo de gráfico/KPI salvo com sucesso.', 3000);
        }
    } catch (Throwable $e) {
        push_toast('danger', 'Erro ao salvar modelo', $e->getMessage(), 4000);
    }

    dso_redirect_novagrafico();
}

/**
 * Exclui modelo (dso_model_grafico).
 */
function dso_handle_model_delete(PDO $pdo): void
{
    if (!isset($_POST['_csrf']) || !dso_csrf_check($_POST['_csrf'], 'csrf_model')) {
        push_toast('danger', 'Erro', 'Token CSRF inválido.', 3500);
        dso_redirect_novagrafico();
    }

    $id = (int) ($_POST['id'] ?? 0);
    if ($id > 0) {
        try {
            $st = $pdo->prepare("DELETE FROM dso_model_grafico WHERE id = :id");
            $st->execute([':id' => $id]);
            push_toast('success', 'Modelo removido', 'Modelo de gráfico/KPI removido.', 3000);
        } catch (Throwable $e) {
            push_toast('danger', 'Erro ao excluir modelo', $e->getMessage(), 4000);
        }
    }

    dso_redirect_novagrafico();
}














/* ============================================================
    IA - GROQ PARA RELATÓRIOS DO DASHBOARD
   ============================================================ */

/**
 * Chama a API de chat da Groq (compatível com OpenAI)
 */
function dso_ia_chat(
    PDO $pdo,
    array $messages,
    string $model = 'llama-3.3-70b-versatile',
    float $temperature = 0.3,
    int $maxTokens = 1024
): array {
    $lang = get_current_language($pdo);                   //  Idioma atual
    $stmt = $pdo->query("SELECT * FROM dso_apikey_ia WHERE id = 1 LIMIT 1");
    $cfg_apikey = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];

    if (!$cfg_apikey['apikey']) {
        return [
            'ok'      => false,
            'content' => null,
            'error'   => $lang->get('erro_apikey'),
        ];
    }

    $url = 'https://api.groq.com/openai/v1/chat/completions';

    $payload = [
        'model'       => $cfg_apikey['model'] ?? 	$model,
        'messages'    => $messages,
        'temperature' => $temperature,
        'max_tokens'  => $maxTokens,
    ];

    $ch = curl_init();

    curl_setopt_array($ch, [
        CURLOPT_URL            => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_HTTPHEADER     => [
            'Authorization: Bearer ' . $cfg_apikey['apikey'],
            'Content-Type: application/json',
        ],
        CURLOPT_POSTFIELDS     => json_encode($payload, JSON_UNESCAPED_UNICODE),
        CURLOPT_TIMEOUT        => 30,
    ]);

    $responseBody = curl_exec($ch);
    $curlErr      = curl_error($ch);
    $httpCode     = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($curlErr) {
        return [
            'ok'      => false,
            'content' => null,
            'error'   => $lang->get('erro_apikey_curl') . $curlErr,
        ];
    }

    if ($httpCode < 200 || $httpCode >= 300) {
        return [
            'ok'      => false,
            'content' => null,
            'error'   => $lang->get('erro_apikey_groq') . $httpCode . '): ' . $responseBody,
        ];
    }

    $json = json_decode($responseBody, true);

    if (!is_array($json)) {
        return [
            'ok'      => false,
            'content' => null,
            'error'   => $lang->get('erro_apikey_groq_json'),
        ];
    }

    $content = $json['choices'][0]['message']['content'] ?? null;

    if ($content === null) {
        return [
            'ok'      => false,
            'content' => null,
            'error'   => $lang->get('erro_apikey_groq_content'),
        ];
    }

    return [
        'ok'      => true,
        'content' => $content,
        'error'   => null,
        'raw'     => $json,
    ];
}

/**
 * Helper: envia um prompt simples para a IA do Dashboard
 * (usa sempre o mesmo system prompt do Smart Office 4.0)
 */
function dso_ia_dashboard_ask(PDO $pdo, string $prompt): string
{
    $lang = get_current_language($pdo);                   //  Idioma atual
    $messages = [
        [
            'role'    => 'system',
            'content' => $lang->get('parametros_ia')
        ],
        [
            'role'    => 'user',
            'content' => $prompt,
        ]
    ];

    $res = dso_ia_chat($pdo, $messages);

    if (!$res['ok']) {
        // Aqui já devolvemos a msg de erro tratada
        return $res['error'] ?: $lang->get('erro_apikey_groq_unknown');
    }

    return $res['content'];
}

/**
 * Texto inicial do chat (menu de opções)
 */
function dso_chat_menu(): string
{
    $pdo = db();
    $lang = get_current_language($pdo);                   //  Idioma atual
    return $lang->get('chat_menu');
}

/**
 * Processa a mensagem do usuário (sempre usando a IA da Groq)
 */
function dso_process_chat_message(PDO $pdo, string $msg, array $dashboardData): string
{
    $msg = trim($msg);
    $lang = get_current_language($pdo);                   //  Idioma atual

    // 1) Relatório completo
    if ($msg === '1') {
        $prompt = $lang->get('parametros_iaa') . $lang->get('parametros_iaaa') . $lang->get('parametros_iaaaa') . json_encode($dashboardData, JSON_UNESCAPED_UNICODE);

        return dso_ia_dashboard_ask($pdo, $prompt);
    }

    // 2) Perguntar qual gráfico
    if ($msg === '2') {
        return $lang->get('ia_graphic');
    }

    // 2 - fase 2: usuário manda "Gráfico: Nome do gráfico"
    if (preg_match('/^Gráfico:/i', $msg)) {
        $nome = trim(str_ireplace('Gráfico:', '', $msg));

        $grafico = null;
        foreach ($dashboardData as $g) {
            if (strcasecmp($g['titulo'], $nome) === 0) {
                $grafico = $g;
                break;
            }
        }

        if (!$grafico) {
            return $lang->get('ia_graphic_not_found');
        }

        $prompt = $lang->get('ia_graphic_analyze') . $lang->get('ia_graphic_analyzee') . $lang->get('ia_graphic_data') . json_encode($grafico, JSON_UNESCAPED_UNICODE);

        return dso_ia_dashboard_ask($pdo, $prompt);
    }

    // 3) Tendências e oportunidades
    if ($msg === '3') {
        $prompt = $lang->get('ia_graphic_analyze_tendencies') . $lang->get('ia_graphic_analyze_tendencies_2') . $lang->get('ia_graphic_analyze_tendencies_data') . json_encode($dashboardData, JSON_UNESCAPED_UNICODE);

        return dso_ia_dashboard_ask($pdo, $prompt);
    }

    // 4) Riscos
    if ($msg === '4') {
        $prompt = $lang->get('ia_graphic_analyze_risks') . $lang->get('ia_graphic_analyze_risks_data') . json_encode($dashboardData, JSON_UNESCAPED_UNICODE);

        return dso_ia_dashboard_ask($pdo, $prompt);
    }

    // 5) Insights automáticos
    if ($msg === '5') {
        $prompt = $lang->get('ia_graphic_analyze_insights') . $lang->get('ia_graphic_analyze_insights_data') . $lang->get('ia_graphic_analyze_insights_data_json') . json_encode($dashboardData, JSON_UNESCAPED_UNICODE);

        return dso_ia_dashboard_ask($pdo, $prompt);
    }

    // Qualquer outra entrada fora do fluxo
    return $lang->get('ia_graphic_analyze_insights_error') . dso_chat_menu();
}
