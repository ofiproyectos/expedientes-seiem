# Expedientes

Sistema de gestión de expedientes SEIEM con Laravel 13, PHP 8.4 y MySQL. La interfaz usa Blade y archivos CSS/JavaScript locales, sin compilación ni dependencias de Node.

## Iniciar en este equipo

La conexión local se configura en `.env`. Las credenciales no se incluyen en `.env.example` ni deben versionarse.

1. Crear la base MySQL `expedientes` y autorizar al usuario de la aplicación sobre ella.
2. Ejecutar `php artisan migrate`.
3. Crear el primer administrador con `php artisan expedientes:admin nombre.usuario --name="Nombre completo"`. La contraseña se solicita oculta en la terminal (mínimo 12 caracteres, letras y números).
4. Ejecutar `php artisan serve` y abrir http://127.0.0.1:8000.
5. En otra terminal ejecutar `php artisan schedule:work` para marcar los permisos expirados. La comprobación de acceso bloquea permisos vencidos incluso sin este proceso.

En una instalación nueva: `composer install`, copiar `.env.example` a `.env`, configurar MySQL y ejecutar `php artisan key:generate` antes de las migraciones. También se puede iniciar el servidor con `composer dev`.

## Funcionamiento

Se inicia sesión con **nombre de usuario y contraseña** (no se usa correo).

1. El **Administrador** crea y edita las cuentas en **Usuarios y permisos**.
2. El **Editor** crea expedientes y administra sus documentos. Los expedientes no tienen estado ni tipos diferentes.
3. El **Administrador** asigna permisos a uno o varios **Lectores**, sobre todo el expediente o documentos seleccionados. Cada permiso de lectura incluye descarga.
4. La asignación por lotes revisa permisos existentes, evita duplicados y consolida los que estén completamente cubiertos, conservando los de mayor vigencia. Se pueden revocar permisos individualmente o por lote.
5. El título del documento determina el nombre del archivo guardado y descargado, manteniendo su extensión. Si se edita el título, la siguiente descarga usa el nuevo nombre.

Usuarios y expedientes se muestran de 10 en 10, con búsquedas dinámicas. Los identificadores internos de roles son `ADMIN`, `LOADER` y `READER`; sus etiquetas visibles son Administrador, Editor y Lector.

Las operaciones se registran en la base de datos. La auditoría no tiene pantalla ni rutas públicas. El historial de versiones sigue disponible para administrar y restaurar documentos.

No hay recuperación por correo ni página de cambio de contraseña propia: el administrador la restablece al editar el usuario. Dejarla vacía conserva la actual; cambiarla cierra las sesiones abiertas de la cuenta.

En producción se debe servir por HTTPS: con `APP_ENV=production` la cookie de sesión se marca como segura (o fijar `SESSION_SECURE_COOKIE=true`).

## Verificar

```powershell
php artisan test
php artisan migrate:status
php artisan route:list --except-vendor
```

Las pruebas usan SQLite en memoria, sin tocar la base MySQL local.

No se crean cuentas con credenciales predeterminadas. Las migraciones históricas se conservan para actualizar instalaciones existentes; no se deben eliminar por referirse a funciones retiradas.

Documentación oficial: https://laravel.com/framework/docs/13.x/installation
