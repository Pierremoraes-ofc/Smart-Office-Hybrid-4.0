
<!-- Barra Superior -->
<nav class="app-header navbar navbar-expand bg-body">
    <!--begin::Container-->
    <div class="container-fluid">
        <!--begin::End Navbar Links-->
        <ul class="navbar-nav ms-auto">
        <!-- Expandir tela -->
         <li class="nav-item">
              <a class="nav-link" data-lte-toggle="sidebar" href="#" role="button">
                <i class="bi bi-list"></i>
              </a>
            </li>
        <li class="nav-item">
            <a class="nav-link" href="#" data-lte-toggle="fullscreen">
            <i data-lte-icon="maximize" class="bi bi-arrows-fullscreen"></i>
            <i data-lte-icon="minimize" class="bi bi-fullscreen-exit" style="display: none"></i>
            </a>
        </li>
        <!-- Expandir tela -->

        </ul>
        <!--end::End Navbar Links-->
    </div>
    <!--end::Container-->
</nav>
<!-- Barra Superior -->







<aside class="app-sidebar bg-body-secondary shadow" data-bs-theme="dark">
    <!-- LOGO -->
    <div class="sidebar-brand">
        <!--begin::Brand Link-->
        <a href="./index.html" class="brand-link">
        <!--begin::Brand Image-->
        <img
            src="../images/favicon.png"
            alt="AdminLTE Logo"
            class="brand-image opacity-75 shadow"
        />
        <!--end::Brand Image-->
        <!--begin::Brand Text-->
        <span class="brand-text fw-light">Smart Office Hybrid 4.0</span>
        <!--end::Brand Text-->
        </a>
        <!--end::Brand Link-->
    </div>
    <!-- LOGO -->


    <!--begin::Sidebar Wrapper-->
    <div class="sidebar-wrapper">
        <nav class="mt-2">
        
        
        <ul
            class="nav sidebar-menu flex-column"
            data-lte-toggle="treeview"
            role="navigation"
            aria-label="Main navigation"
            data-accordion="false"
            id="navigation"
        >
            <!-- dashboard.php -->
            <li class="nav-item">
                <a href="./dashboard" class="nav-link">
                    <i class="nav-icon bi bi-speedometer"></i>
                    <p><?= $lang->get('dashboard'); ?></p>
                </a>
            </li>
            <!-- dashboard.php -->
            
            <!-- list_database.php -->
            <li class="nav-item">
                <a href="./novagrafico" class="nav-link">
                    <i class="nav-icon bi bi-card-list"></i>
                    <p><?= $lang->get('list_database'); ?></p>
                </a>
            </li>
            <!-- list_database.php -->
            
            <!-- ia.php -- >
            <li class="nav-item">
                <a href="./ia" class="nav-link">
                    <i class="nav-icon bi bi-openai"></i>
                    <p><?= $lang->get('relatorio'); ?></p>
                </a>
            </li>
            <! -- ia.php -->
            
            <!-- preferences.php -->
            <li class="nav-item">
                <a href="./preferences" class="nav-link">
                    <i class="nav-icon bi bi-gear"></i>
                    <p><?= $lang->get('preferencias'); ?></p>
                </a>
            </li>
            <!-- preferences.php -->


            <li class="nav-header">LABELS</li>
            <li class="nav-item">
            <a href="#" class="nav-link">
                <i class="nav-icon bi bi-circle text-danger"></i>
                <p class="text">Important</p>
            </a>
            </li>
            <li class="nav-item">
            <a href="#" class="nav-link">
                <i class="nav-icon bi bi-circle text-warning"></i>
                <p>Warning</p>
            </a>
            </li>
            <li class="nav-item">
            <a href="#" class="nav-link">
                <i class="nav-icon bi bi-circle text-info"></i>
                <p>Informational</p>
            </a>
            </li>
            
            <!-- preferences.php -->
            <li class="nav-item">
                <a href="./logout" class="nav-link">
                    <i class="nav-icon bi bi-box-arrow-in-right"></i>
                    <p><?= $lang->get('logout'); ?></p>
                </a>
            </li>
            <!-- preferences.php -->
        </ul>
        <!--end::Sidebar Menu-->
        </nav>
    </div>
    <!--end::Sidebar Wrapper-->
</aside>