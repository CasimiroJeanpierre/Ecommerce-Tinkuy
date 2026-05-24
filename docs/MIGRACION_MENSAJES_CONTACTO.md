# Migración de la tabla `mensajes_contacto`

La funcionalidad de administración de mensajes requiere dos columnas nuevas en la tabla `mensajes_contacto`:

- `leido` (TINYINT(1) NOT NULL DEFAULT 0)
- `estado` (ENUM('pendiente','respondido','archivado') NOT NULL DEFAULT 'pendiente')

## 1. Estado actual esperado
Si tu estructura actual solo tiene:
```
id_mensaje, nombre, email, asunto, mensaje, fecha_envio, leido
```
Debes agregar la columna `estado`.

## 2. Scripts SQL de migración
Ejecuta esto en la base de datos `tinkuy_db` (phpMyAdmin o consola):
```sql
ALTER TABLE mensajes_contacto
    ADD COLUMN estado ENUM('pendiente','respondido','archivado') NOT NULL DEFAULT 'pendiente' AFTER leido;
```

Si no existiera la columna `leido` (ya existe en tu caso), añade:
```sql
ALTER TABLE mensajes_contacto
    ADD COLUMN leido TINYINT(1) NOT NULL DEFAULT 0 AFTER fecha_envio;
```

## 3. Normalización de datos (opcional)
Si llegasen a existir registros con estado NULL o vacío:
```sql
UPDATE mensajes_contacto SET estado = 'pendiente' WHERE estado IS NULL OR estado = '';
```

## 4. Verificación
Comprobar la nueva estructura:
```sql
SHOW COLUMNS FROM mensajes_contacto;
```
Debe mostrar al menos:
```
id_mensaje | nombre | email | asunto | mensaje | fecha_envio | leido | estado
```

## 5. Impacto en el código
- El modelo `Mensaje` ya soporta fallback si `estado` no existe (simula todos como 'pendiente').
- Una vez añadida la columna, los filtros y acciones (responder / archivar) funcionarán plenamente.

## 6. Recomendación
Aplica la migración cuanto antes para tener estadísticas reales (pendientes vs respondidos vs archivados).

## 7. Rollback (si fuera necesario)
```sql
ALTER TABLE mensajes_contacto DROP COLUMN estado;
```
(No recomendado, perderías funcionalidad del panel de mensajes.)

---
Documento generado automáticamente para soporte de migración.
