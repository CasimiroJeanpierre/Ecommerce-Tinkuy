<?php
// Vista parcial para la navegación del vendedor
// Incluir este archivo en todas las vistas de vendedor
?>
<nav class="navbar navbar-expand-lg navbar-dark bg-success sticky-top shadow-sm">
    <div class="container">
        <a class="navbar-brand d-flex align-items-center" href="<?= $base_url ?>?page=vendedor_dashboard">
            <i class="bi bi-shop me-2"></i><span style="font-weight: bold; letter-spacing: 1px;">Tinkuy Vendedor</span>
        </a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#vendedorNavbar">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="vendedorNavbar">
            <ul class="navbar-nav me-auto mb-2 mb-lg-0">
                <li class="nav-item">
                    <a class="nav-link<?= $page === 'vendedor_productos' ? ' active' : '' ?>" 
                       href="<?= $base_url ?>?page=vendedor_productos">
                        <i class="bi bi-box-seam-fill me-1"></i>Mis Productos
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link<?= $page === 'vendedor_envios' ? ' active' : '' ?> position-relative" 
                       href="<?= $base_url ?>?page=vendedor_envios">
                        <i class="bi bi-truck me-1"></i>Envíos Pendientes
                        <?php if (isset($envios_pendientes) && $envios_pendientes > 0): ?>
                        <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-warning text-dark">
                            <?= $envios_pendientes ?>
                            <span class="visually-hidden">envíos pendientes</span>
                        </span>
                        <?php endif; ?>
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link<?= $page === 'vendedor_ventas' ? ' active' : '' ?>" 
                       href="<?= $base_url ?>?page=vendedor_ventas">
                        <i class="bi bi-bar-chart-line-fill me-1"></i>Mis Ventas
                    </a>
                </li>
            </ul>
            <ul class="navbar-nav">
                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle<?= $page === 'mi_perfil_vendedor' ? ' active' : '' ?>" 
                       href="#" id="navbarUserDropdown" role="button" 
                       data-bs-toggle="dropdown" aria-expanded="false">
                        <i class="bi bi-person-circle me-1"></i><?= htmlspecialchars($nombre_vendedor_sesion ?? $_SESSION['usuario']) ?>
                    </a>
                    <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="navbarUserDropdown">
                        <li>
                            <a class="dropdown-item<?= $page === 'mi_perfil_vendedor' ? ' active' : '' ?>" 
                               href="<?= $base_url ?>?page=mi_perfil_vendedor">
                                <i class="bi bi-person-badge me-2"></i>Mi Perfil
                            </a>
                        </li>
                        <li><hr class="dropdown-divider"></li>
                        <li>
                            <a class="dropdown-item text-danger" href="<?= $base_url ?>?page=logout">
                                <i class="bi bi-box-arrow-right me-2"></i>Cerrar Sesión
                            </a>
                        </li>
                    </ul>
                </li>
            </ul>
        </div>
    </div>
</nav>