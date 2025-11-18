<?php
require_once __DIR__ . '/../api/functions.php';
if (session_status() === PHP_SESSION_NONE) session_start();
$lang = get_current_language(db());

$redirectUrl   = $_SESSION['redirect_target'] ?? '/SmartOfficeHybrid/';
$delayMs       = $_SESSION['redirect_delay']  ?? 5000;

// Limpa os dados após uso (boa prática)
unset($_SESSION['redirect_target'], $_SESSION['redirect_delay']);
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
  <meta charset="UTF-8">
  <title><?= $lang->get('loading'); ?></title>
  <?php include 'includes/header_login.php'; ?>
  <script>
    // Espera o tempo total e redireciona
    setTimeout(() => {
      window.location.href = "<?= htmlspecialchars($redirectUrl) ?>";
    }, <?= (int)$delayMs ?>);
  </script>
</head>
<body class="login-page bg-body-secondary">
  <div class="login-box">
    <div class="card card-outline card-primary text-center">
      <div class="card-body login-card-body">
        <h3><?= $lang->get('smartoffice'); ?></h3>
        <p><small><?= $lang->get('redirecionando'); ?></small></p>
      </div>
    </div>
  </div>
  <?php include 'includes/footer_login.php'; ?>
</body>
</html>
