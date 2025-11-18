
  <footer class="app-footer">
  <!--begin::To the end-->
  <div class="float-end d-none d-sm-inline">Anything you want</div>
  <!--end::To the end-->
  <!--begin::Copyright-->
  <strong>
      Copyright &copy; 2014-2025&nbsp;
      <a href="https://github.com/Pierremoraes-ofc" class="text-decoration-none">PierreMoraes</a>.
  </strong>
  All rights reserved.
  <!--end::Copyright-->
  </footer>
  <!--end::Footer-->
</div>

<script src="https://cdn.jsdelivr.net/npm/overlayscrollbars@2.11.0/browser/overlayscrollbars.browser.es6.min.js"
  crossorigin="anonymous"
></script>

<script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.11.8/dist/umd/popper.min.js"
  crossorigin="anonymous"
></script>


<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.7/dist/js/bootstrap.min.js"
  crossorigin="anonymous"
></script>

<script src="https://cdn.jsdelivr.net/npm/apexcharts@3.37.1/dist/apexcharts.min.js"
  integrity="sha256-+vh8GkaU7C9/wbSLIcwq82tQ2wTf44aOHA8HlBMwRI8=" crossorigin="anonymous">
</script>

<script src="https://cdn.jsdelivr.net/npm/apexcharts@3.37.1/dist/apexcharts.min.js"
  integrity="sha256-+vh8GkaU7C9/wbSLIcwq82tQ2wTf44aOHA8HlBMwRI8=" crossorigin="anonymous">
</script>

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
<!--end::OverlayScrollbars Configure-->
<!-- OPTIONAL SCRIPTS -->
<!-- apexcharts -->



<script src="../AdminLTE/js/adminlte.min.js"></script>
<?php if (!empty($_SESSION['flash_toast_queue']) && is_array($_SESSION['flash_toast_queue'])): ?>
  <div id="dso-toast-container"
       class="toast-container position-fixed top-0 end-0 p-3"
       style="z-index: 1080"></div>

  <script>
  (function(){
    var queue = <?= json_encode($_SESSION['flash_toast_queue'], JSON_UNESCAPED_UNICODE) ?>;

    function classByType(t){
      t = (t||'').toLowerCase();
      // mapeia para classes Bootstrap 5 / AdminLTE
      if (t === 'success') return 'text-bg-success';
      if (t === 'danger')  return 'text-bg-danger';
      if (t === 'warning') return 'text-bg-warning';
      if (t === 'primary') return 'text-bg-primary';
      if (t === 'secondary') return 'text-bg-secondary';
      if (t === 'dark')    return 'text-bg-dark';
      return 'text-bg-info';
    }

    function showToastAdminLTE3(toast){
      // API antiga (AdminLTE 3)
      $(document).Toasts('create', {
        class: classByType(toast.type).replace('text-bg','bg'),
        title: toast.title || '',
        body:  toast.message || '',
        autohide: true,
        delay: (toast.delay && Number(toast.delay)) ? Number(toast.delay) : 3000
      });
    }

    function showToastBS5(toast){
      // Bootstrap 5 / AdminLTE 4
      var container = document.getElementById('dso-toast-container');
      var el = document.createElement('div');
      el.className = 'toast align-items-center '+classByType(toast.type)+' border-0';
      el.setAttribute('role','alert');
      el.setAttribute('aria-live','assertive');
      el.setAttribute('aria-atomic','true');

      var title = toast.title ? '<strong class="me-2">'+toast.title+'</strong>' : '';
      el.innerHTML =
        '<div class="d-flex">'+
          '<div class="toast-body">'+ title + (toast.message || '') +'</div>'+
          '<button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="Close"></button>'+
        '</div>';

      container.appendChild(el);
      var delay = (toast.delay && Number(toast.delay)) ? Number(toast.delay) : 3000;
      try {
        var t = new bootstrap.Toast(el, { delay: delay, autohide: true });
        t.show();
      } catch(e) {
        // fallback simples se bootstrap.Toast não existir
        el.style.display = 'block';
        setTimeout(function(){ el.remove(); }, delay);
      }
    }

    document.addEventListener('DOMContentLoaded', function(){
      var isAdminLTE3 = (window.jQuery && typeof $(document).Toasts === 'function');

      // Se quiser fila sequencial (um depois do outro), acumule delays:
      var acc = 0;
      queue.forEach(function(toast){
        var d = (toast.delay && Number(toast.delay)) ? Number(toast.delay) : 3000;
        setTimeout(function(){
          if (isAdminLTE3) showToastAdminLTE3(toast);
          else showToastBS5(toast);
        }, acc);
        acc += Math.min(d, 8000); // evita fila muito lenta
      });
    });
  })();
  </script>
  <?php $_SESSION['flash_toast_queue'] = []; // limpa a fila ?>
<?php endif; ?>
