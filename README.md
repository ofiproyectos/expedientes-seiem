# Expedientes

Base inicial de gestión de expedientes con Laravel 13, PHP 8.4 y MySQL.

## Iniciar en este equipo

La conexión local se configura en `.env`. Las credenciales no se incluyen en `.env.example` ni deben versionarse.

1. Crear la base MySQL `expedientes` y autorizar al usuario de la aplicación sobre ella.
2. Ejecutar `php artisan migrate`.
3. Crear el primer administrador con `php artisan expedientes:admin nombre.usuario --name="Nombre completo"`. La contraseña se solicita oculta en la terminal (mínimo 12 caracteres, letras y números).
4. Ejecutar `php artisan serve` y abrir http://127.0.0.1:8000.
5. En otra terminal ejecutar `php artisan schedule:work` para marcar los permisos expirados. La comprobación de acceso bloquea permisos vencidos incluso sin este proceso.

En una instalación nueva: `composer install`, copiar `.env.example` a `.env`, configurar MySQL y ejecutar `php artisan key:generate` antes de las migraciones. Las pantallas iniciales usan CSS local y no requieren Node ni Vite.

## Flujo inicial

Se inicia sesión con **nombre de usuario y contraseña** (no se usa correo).

1. El ADMIN crea las cuentas en **Usuarios y permisos** (nombre completo, nombre de usuario, rol ADMIN, LOADER o READER, estado y contraseña).
2. Un LOADER crea el expediente (queda en *Activo*, con él como responsable) y carga uno o varios documentos con su tipo. Al concluir, puede pasarlo a *Archivado*.
3. El ADMIN concede lectura a un READER sobre el expediente completo o un documento, con descarga opcional.
4. El ADMIN consulta todo el historial en **Auditoría**.

No hay recuperación por correo: si alguien olvida su contraseña, el ADMIN la restablece desde la ficha del usuario. Cada usuario puede cambiar la suya en **Cambiar contraseña**.

En producción se debe servir por HTTPS: con `APP_ENV=production` la cookie de sesión se marca como segura (o fijar `SESSION_SECURE_COOKIE=true`).

## Verificar

```powershell
php artisan test
php artisan migrate:status
php artisan route:list --except-vendor
```

Las pruebas usan SQLite en memoria, sin tocar la base MySQL local.

Consultar [decisiones, cobertura y pendientes](docs/REQUISITOS.md). No se crean cuentas con credenciales predeterminadas.

Documentación oficial: https://laravel.com/framework/docs/13.x/installation
