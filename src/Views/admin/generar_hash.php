<?php
/**
 * Utilidad de generación de hash para la contraseña del administrador (archivo de desarrollo).
 * Genera un hash bcrypt/argon2 de la contraseña 'admin123' para uso en seeds de BD.
 * Este archivo NO debe quedar expuesto en producción ya que revela el hash de la contraseña admin.
 *
 * @deprecated Eliminar o bloquear el acceso antes de desplegar en producción.
 *             Usar PHP CLI: php -r "echo password_hash('admin123', PASSWORD_DEFAULT);"
 */
// Genera hash de la contraseña de admin para insertar en la tabla usuarios
echo password_hash("admin123", PASSWORD_DEFAULT);
?>
