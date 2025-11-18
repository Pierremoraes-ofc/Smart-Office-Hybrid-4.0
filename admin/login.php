<?php
// === Funções obrigatorias ===
require_once __DIR__ . '/../api/functions.php';
require_once __DIR__ . '/../api/language.php';       // LanguageManager

Logged_in();                                        //  Verifica se usuário ja esta logado
$pdo      = db();
$settings = get_dso_settings($pdo);
$lang     = get_current_language($pdo);

// CSRF do login
$CSRF = dso_csrf_login_token();

// Se enviou POST, delega pro handler centralizado
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  dso_handle_post_login($pdo, $_POST, $lang);
  exit;
}
?>


<!doctype html>
<html lang="en">
<!--begin::Head-->

<head>

  <title>Login - Smart Office 4.0</title>
  <?php include 'includes/header_login.php'; ?>

</head>
<!--end::Head-->
<!--begin::Body-->

<body class="login-page bg-body-secondary">
  <div class="login-box">
    <div class="card card-outline card-primary">
      <div class="card-header">
        <a href="../AdminLTE/index2.html"
          class="link-dark text-center link-offset-2 link-opacity-100 link-opacity-50-hover">
          <h1 class="mb-0"><b>Smart Office Hybrid</b> 4.0</h1>
        </a>
      </div>
      <div class="card-body login-card-body">
        <p class="login-box-msg"><?= $lang->get('faca_login'); ?></p>
        <form method="post" action="" autocomplete="off" novalidate>
            <input type="hidden" name="_csrf" value="<?= htmlspecialchars($CSRF) ?>">

            <div class="input-group mb-1">
              <div class="form-floating">
                <input id="loginUsername" name="username" type="text" class="form-control"
                       placeholder="<?= htmlspecialchars($lang->get('usuario')); ?>" required>
                <label for="loginUsername"><?= htmlspecialchars($lang->get('usuario')); ?></label>
              </div>
              <div class="input-group-text"><span class="bi bi-envelope"></span></div>
            </div>

            <div class="input-group mb-1">
              <div class="form-floating">
                <input id="loginPassword" name="password" type="password" class="form-control"
                       placeholder="<?= htmlspecialchars($lang->get('senha')); ?>" required>
                <label for="loginPassword"><?= htmlspecialchars($lang->get('senha')); ?></label>
              </div>
              <div class="input-group-text"><span class="bi bi-lock-fill"></span></div>
            </div>

            <div class="row">
              <div class="col-8 d-inline-flex align-items-center">
                <div class="form-check">
                  <input class="form-check-input" type="checkbox" value="1" id="flexCheckDefault">
                  <label class="form-check-label" for="flexCheckDefault">
                    <?= htmlspecialchars($lang->get('lembrar')); ?>
                  </label>
                </div>
              </div>
              <div class="col-4">
                <div class="d-grid gap-2">
                  <button type="submit" class="btn btn-primary">
                    <?= htmlspecialchars($lang->get('entrar')); ?>
                  </button>
                </div>
              </div>
            </div>
          </form>
        <!-- /.social-auth-links -->
        <!-- <p class="mb-1"><a href="forgot-password.html"><?= $lang->get('esqueci_senha'); ?></a></p>-->
      </div>
      <!-- /.login-card-body -->
    </div>
  </div>
  <!-- /.login-box -->
  <?php include 'includes/footer_login.php'; ?>
</body>
<!--end::Body-->

</html>