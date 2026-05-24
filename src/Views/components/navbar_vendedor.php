<?php
// Si el controlador no ha preparado las variables necesarias
if (!isset($nombre_vendedor)) {
    $nombre_vendedor = $_SESSION['usuario'] ?? 'Usuario';
}
if (!isset($envios_pendientes)) {
    $envios_pendientes = 0;
}
?>
<style>
    /* Navbar más moderna */
    .navbar {
        box-shadow: 0 2px 4px rgba(0,0,0,.08);
        padding-top: 0.8rem;
        padding-bottom: 0.8rem;
    }
    .navbar .nav-link {
        font-weight: 500;
        color: rgba(255,255,255,0.8);
        transition: color 0.2s;
    }
    .navbar .nav-link:hover, .navbar .nav-link.active {
        color: #fff;
    }
</style>

<nav class="navbar navbar-expand-lg navbar-dark bg-success sticky-top">
    <div class="container">
        <a class="navbar-brand" href="<?= $base_url ?>?page=vendedor_dashboard">
            <i class="bi bi-shop me-2"></i>Tinkuy Vendedor
        </a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#vendedorNavbar">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="vendedorNavbar">
            <ul class="navbar-nav me-auto mb-2 mb-lg-0">
                <li class="nav-item">
                    <a class="nav-link" href="<?= $base_url ?>?page=index">
                        <i class="bi bi-globe me-1"></i>Ver Tienda
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?= $pagina_actual === 'productos' ? 'active' : '' ?>" 
                       href="<?= $base_url ?>?page=vendedor_productos">
                        <i class="bi bi-box-seam-fill me-1"></i>Mis Productos
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?= $pagina_actual === 'envios' ? 'active' : '' ?> position-relative" 
                       href="<?= $base_url ?>?page=vendedor_envios">
                        <i class="bi bi-truck me-1"></i>Envíos Pendientes
                        <?php if ($envios_pendientes > 0): ?>
                            <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-warning text-dark">
                                <?= $envios_pendientes ?>
                                <span class="visually-hidden">envíos pendientes</span>
                            </span>
                        <?php endif; ?>
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?= $pagina_actual === 'ventas' ? 'active' : '' ?>" 
                       href="<?= $base_url ?>?page=vendedor_ventas">
                        <i class="bi bi-bar-chart-line-fill me-1"></i>Mis Ventas
                    </a>
                </li>
            </ul>
            <ul class="navbar-nav">
                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle" href="#" id="navbarDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                        <i class="bi bi-person-circle me-1"></i><?= htmlspecialchars($nombre_vendedor) ?>
                    </a>
                    <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="navbarDropdown">
                        <li>
                            <a class="dropdown-item" href="<?= $base_url ?>?page=mi_perfil_vendedor">
                                <i class="bi bi-gear me-2"></i>Mi Perfil
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