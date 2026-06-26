<?php
/**
 * Utilidad de generación de hash para contraseñas (archivo de desarrollo).
 * Genera un hash bcrypt de la contraseña '123456' para uso en pruebas o seeds de BD.
 * Este archivo NO debe quedar expuesto en producción ya que revela el hash de una
 * contraseña de ejemplo.
 *
 * @deprecated Eliminar o proteger este archivo antes de desplegar en producción.
 *             Usar PHP CLI o un script de seed separado para generar hashes de BD.
 */
// Genera hash bcrypt para poblar la BD en desarrollo/testing
echo password_hash('123456', PASSWORD_BCRYPT);
?>
