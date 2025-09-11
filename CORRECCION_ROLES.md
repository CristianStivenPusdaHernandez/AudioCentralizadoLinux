# Corrección de Roles y Permisos

## Problema Identificado
Los roles "operador" y "reproductor" tenían acceso a las mismas funciones. El rol "reproductor" debería solo poder reproducir audios, no editarlos, eliminarlos o agregar nuevos.

## Solución Implementada

### 1. Estructura de Roles Corregida

**Administrador:**
- Acceso completo al sistema
- Puede gestionar usuarios
- Puede gestionar audios (crear, editar, eliminar)
- Puede editar categorías

**Operador:**
- Puede gestionar audios (crear, editar, eliminar)
- Puede editar categorías
- NO puede gestionar usuarios

**Reproductor:**
- Solo puede reproducir audios
- NO puede crear, editar o eliminar audios
- NO puede editar categorías
- NO puede gestionar usuarios

### 2. Archivos Modificados

1. **`public/js/main.js`**
   - Corregida lógica de permisos para ocultar botones de edición/eliminación al rol "reproductor"
   - Ocultado el botón FAB (agregar audio) para el rol "reproductor"
   - Permitido al rol "operador" editar categorías

2. **`app/controllers/AudioController.php`**
   - Permitido al rol "operador" editar audios y categorías
   - Mantenida restricción para el rol "reproductor"

3. **`app/models/User.php`**
   - Mejorada consulta de permisos
   - Considerados solo usuarios activos

### 3. Configuración de Base de Datos

#### Opción A: Ejecutar Script PHP (Recomendado)
```bash
php setup_database.php
```

#### Opción B: Ejecutar Script SQL
```sql
-- Ejecutar el contenido del archivo fix_roles.sql en tu base de datos
```

### 4. Usuarios por Defecto

Después de ejecutar el script de configuración:

- **Usuario:** admin
- **Contraseña:** admin123
- **Rol:** administrador

### 5. Verificación

1. Inicia sesión como administrador
2. Crea usuarios de prueba con roles "operador" y "reproductor"
3. Verifica que:
   - **Reproductor:** Solo ve botones de reproducir, no de editar/eliminar/agregar
   - **Operador:** Ve todos los botones excepto gestión de usuarios
   - **Administrador:** Ve todos los botones

### 6. Permisos por Rol

| Permiso | Administrador | Operador | Reproductor |
|---------|---------------|----------|-------------|
| Reproducir audio | ✅ | ✅ | ✅ |
| Subir audio | ✅ | ✅ | ❌ |
| Editar audio | ✅ | ✅ | ❌ |
| Eliminar audio | ✅ | ✅ | ❌ |
| Editar categorías | ✅ | ✅ | ❌ |
| Gestionar usuarios | ✅ | ❌ | ❌ |

## Notas Importantes

1. **Backup:** Haz un respaldo de tu base de datos antes de ejecutar los scripts
2. **Usuarios existentes:** Los usuarios existentes mantendrán sus roles actuales
3. **Contraseña por defecto:** Cambia la contraseña del administrador después de la primera configuración
4. **Permisos en frontend:** Los botones se ocultan según el rol, pero la validación real se hace en el backend

## Troubleshooting

Si después de aplicar los cambios sigues viendo problemas:

1. Verifica que la base de datos tenga las tablas correctas
2. Confirma que los usuarios tengan los roles asignados correctamente
3. Limpia la caché del navegador
4. Verifica que no haya errores en la consola del navegador