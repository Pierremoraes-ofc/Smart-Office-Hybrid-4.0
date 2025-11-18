

<script
    src="https://cdn.jsdelivr.net/npm/overlayscrollbars@2.11.0/browser/overlayscrollbars.browser.es6.min.js"
    crossorigin="anonymous"
></script>

<script
    src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.11.8/dist/umd/popper.min.js"
    crossorigin="anonymous"
></script>

<script
    src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.7/dist/js/bootstrap.min.js"
    crossorigin="anonymous"
></script>

<script src="../AdminLTE/js/adminlte.js"></script>

<script>
    const SELECTOR_SIDEBAR_WRAPPER = '.sidebar-wrapper';
    const Default = {
    scrollbarTheme: 'os-theme-light',
    scrollbarAutoHide: 'leave',
    scrollbarClickScroll: true,
    };
    document.addEventListener('DOMContentLoaded', function () {
    const sidebarWrapper = document.querySelector(SELECTOR_SIDEBAR_WRAPPER);
    if (sidebarWrapper && OverlayScrollbarsGlobal?.OverlayScrollbars !== undefined) {
        OverlayScrollbarsGlobal.OverlayScrollbars(sidebarWrapper, {
        scrollbars: {
            theme: Default.scrollbarTheme,
            autoHide: Default.scrollbarAutoHide,
            clickScroll: Default.scrollbarClickScroll,
        },
        });
    }
    });
</script>

<!-- Certifique-se de ter o Bootstrap Bundle -->
<script src="/SmartOfficeHybrid/AdminLTE/plugins/bootstrap/js/bootstrap.bundle.min.js"></script>

<script>
function showToast(type, title, message, delayMs) {
  const map = {
    success:'bg-success text-white', danger:'bg-danger text-white',
    info:'bg-info text-dark', warning:'bg-warning text-dark',
    primary:'bg-primary text-white', secondary:'bg-secondary text-white',
    dark:'bg-dark text-white', light:'bg-light text-dark'
  };
  const cls = map[type] || 'bg-primary text-white';
  const id  = 't_' + Math.random().toString(36).slice(2);
  const delay = Number(delayMs) > 0 ? Number(delayMs) : 3000;

  const html = `
  <div id="${id}" class="toast align-items-center border-0 ${cls}" role="alert" aria-live="assertive" aria-atomic="true" data-bs-delay="${delay}">
    <div class="d-flex">
      <div class="toast-body">
        <strong>${title ? title + ' — ' : ''}</strong>${message}
      </div>
      <button type="button" class="btn-close ${cls.includes('text-white') ? 'btn-close-white' : ''} me-2 m-auto" data-bs-dismiss="toast" aria-label="Close"></button>
    </div>
  </div>`;
  const area = document.getElementById('toast-area') || document.body;
  const wrap = document.createElement('div'); wrap.innerHTML = html.trim();
  const el = wrap.firstElementChild; area.appendChild(el);
  new bootstrap.Toast(el).show();
}
</script>



<script src="/AdminLTE/plugins/jquery/jquery.min.js"></script>
<script src="/AdminLTE/dist/js/adminlte.min.js"></script>
<?php
// Lê a FILA de toasts (pode haver vários) e agenda em sequência
if (session_status() === PHP_SESSION_NONE) { session_start(); }

if (!empty($_SESSION['flash_toast_queue']) && is_array($_SESSION['flash_toast_queue'])) {
  $queue = $_SESSION['flash_toast_queue'];
  unset($_SESSION['flash_toast_queue']); // consome uma vez

  // Agenda em cascata: cada toast 3s; aplica pequeno espaçamento (200ms)
  $offset = 0;
  foreach ($queue as $t) {
    $type  = htmlspecialchars($t['type'] ?? 'info', ENT_QUOTES, 'UTF-8');
    $title = htmlspecialchars($t['title'] ?? '', ENT_QUOTES, 'UTF-8');
    $msg   = htmlspecialchars($t['message'] ?? '', ENT_QUOTES, 'UTF-8');
    $delay = (int)($t['delay'] ?? 3000);
    $when  = $offset;

    echo "<script>
      setTimeout(function(){
        showToast('{$type}','{$title}','{$msg}', {$delay});
      }, {$when});
    </script>";

    $offset += $delay + 200; // próxima notificação depois de 3s + 200ms
  }
}
?>
