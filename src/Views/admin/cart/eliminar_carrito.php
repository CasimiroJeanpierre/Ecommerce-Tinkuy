<?php
session_start();
if (isset($_GET['id']) && isset($_SESSION['carrito'][$_GET['id']])) {
    unset($_SESSION['carrito'][$_GET['id']]);
}
header("Location: cart.php");
exit();
