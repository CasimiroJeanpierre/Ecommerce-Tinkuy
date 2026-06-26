<?php
/**
 * Componente de barra de navegación pública de la tienda Tinkuy.
 * Renderiza la barra superior responsive con:
 *   - Logo y nombre de la tienda (enlace al inicio)
 *   - Enlace al catálogo de productos
 *   - Icono del carrito con contador dinámico (desde $_SESSION['carrito'])
 *   - Dropdown de usuario: login/registro si no autenticado, o perfil+panel si lo está
 *   - El panel al que lleva el dropdown varía según rol: cliente → mi_perfil,
 *     vendedor → vendedor_dashboard, admin → admin_dashboard
 *
 * Variables disponibles desde el scope de inclusión:
 *   $base_url    (string) - URL base del proyecto para construir los href de navegación
 *   $pagina_actual (string) - Identificador de la página activa (para resaltar el enlace)
 */
// src/Views/components/navbar.php

// --- RUTAS BASE (Portables) ---
$project_root = defined('PROJECT_ROOT') ? PROJECT_ROOT : "/Ecommerce-Tinkuy";
// El "Cerebro" al que apuntan todas las VISTAS
$base_url = defined('BASE_URL') ? BASE_URL : ($project_root . "/public/index.php"); 

// --- VARIABLES DE SESIÓN ---
$pagina_actual = $pagina_actual ?? '';
$is_logged_in = isset($_SESSION['usuario_id']);
$user_role = $_SESSION['rol'] ?? null;
$display_name = htmlspecialchars($_SESSION['usuario'] ?? 'Mi Cuenta');
?>

<nav class="navbar navbar-expand-lg navbar-dark bg-dark sticky-top shadow-sm">
    <div class="container">
        
        <a class="navbar-brand d-flex align-items-center" href="<?= $base_url ?>?page=index">
            <img src="<?= $project_root ?>/public/img/Logo.png" style="width: 50px; height: auto; margin-right: 10px;" alt="LogoTinkuy" /> 
            <span style="font-weight: bold; letter-spacing: 1px;">Tinkuy</span>
        </a>
        
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
            <span class="navbar-toggler-icon"></span>
        </button>

        <div class="collapse navbar-collapse" id="navbarNav">
            <ul class="navbar-nav ms-auto mb-2 mb-lg-0">

                <!-- Mostrar navegación de tienda para todos los usuarios logueados -->
                <li class="nav-item">
                    <a class="nav-link <?= ($pagina_actual == 'index' ? 'active fw-bold' : ''); ?>" href="<?= $base_url ?>?page=index">
                        <i class="bi bi-house-door me-1"></i> Inicio
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?= ($pagina_actual == 'nosotros' ? 'active fw-bold' : ''); ?>" href="<?= $base_url ?>?page=about">
                        <i class="bi bi-info-circle me-1"></i> Nosotros
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?= ($pagina_actual == 'productos' ? 'active fw-bold' : ''); ?>" href="<?= $base_url ?>?page=products">
                        <i class="bi bi-shop me-1"></i> Productos
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?= ($pagina_actual == 'contacto' ? 'active fw-bold' : ''); ?>" href="<?= $base_url ?>?page=contact">
                        <i class="bi bi-envelope me-1"></i> Contacto
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link position-relative <?= ($pagina_actual == 'carrito' ? 'active fw-bold' : ''); ?>" href="<?= $base_url ?>?page=cart">
                        <i class="bi bi-cart me-1"></i> Carrito
                    </a>
                </li>

                <?php if ($is_logged_in): ?>
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle text-warning" href="#" id="navbarUserDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                            <i class="bi bi-person-circle me-1"></i> <?= $display_name ?>
                        </a>
                        <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="navbarUserDropdown">
                            
                            <!-- Opciones comunes para todos los usuarios logueados -->
                            <li><a class="dropdown-item" href="<?= $base_url ?>?page=mi_perfil"><i class="bi bi-person-lines-fill me-2"></i>Mi Perfil</a></li>
                            <li><a class="dropdown-item" href="<?= $base_url ?>?page=pedidos"><i class="bi bi-box-seam me-2"></i>Mis Pedidos</a></li>
                            
                            <?php if ($user_role === 'vendedor'): ?>
                                <li><hr class="dropdown-divider"></li>
                                <li><a class="dropdown-item" href="<?= $base_url ?>?page=vendedor_dashboard"><i class="bi bi-speedometer2 me-2"></i>Panel Vendedor</a></li>
                            
                            <?php elseif ($user_role === 'admin'): ?>
                                <li><hr class="dropdown-divider"></li>
                                <li><a class="dropdown-item" href="<?= $base_url ?>?page=admin_dashboard"><i class="bi bi-shield-lock-fill me-2"></i>Panel Admin</a></li>
                            
                            <?php endif; ?>
                            
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item text-danger" href="<?= $base_url ?>?page=logout"><i class="bi bi-box-arrow-right me-2"></i>Cerrar Sesión</a></li>
                        </ul>
                    </li>

                <?php else: // Si NO está logueado ?>
                    
                    <li class="nav-item">
                        <a class="nav-link <?= ($pagina_actual == 'login' ? 'active fw-bold' : ''); ?>" href="<?= $base_url ?>?page=login">
                            <i class="bi bi-box-arrow-in-right me-1"></i> Iniciar Sesión
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?= ($pagina_actual == 'registro' ? 'active fw-bold' : ''); ?>" href="<?= $base_url ?>?page=register">
                            <i class="bi bi-person-plus-fill me-1"></i> Registrarse
                        </a>
                    </li>
                <?php endif; ?>
            </ul>
        </div>
    </div>
</nav>