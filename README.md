# Expedientes SEIEM

Una implementación de expedientes, documentos, versiones, permisos y auditoría, con desarrollo autónomo y producción integrada a la Plataforma Institucional SEIEM. PHP 8.4, Laravel 13 y Blade; no requiere Node. Desarrollo puede usar SQLite; producción conserva MySQL y sus archivos persistentes.

## Ramas e historia

`main` permanece intacta. `independiente` conserva el commit `117c8e9`, anterior a la integración. `Plataforma` conserva la fuente institucional contrastada con el servidor antes de esta reorganización. `unificacion` concentra el desarrollo futuro. Las dos primeras son referencias históricas, no variantes que deban recibir cada función por separado.

El diagnóstico, la arquitectura y los pendientes están en [docs/UNIFICACION.md](docs/UNIFICACION.md).

## Desarrollo sin portal ni Keycloak

En un checkout nuevo, desde la raíz, con PHP 8.4, Composer y extensiones PDO SQLite, mbstring, XML, fileinfo, cURL, ZIP e Intl:

```powershell
Copy-Item .env.example .env
New-Item -ItemType File database/dev.sqlite
composer install --no-interaction --prefer-dist
php artisan key:generate
php artisan migrate
php artisan expedientes:demo
composer dev
```

No reemplazar un `.env` ni una base existentes. El ejemplo selecciona `APP_ENV=local`, `OIDC_ENABLED=false`, SQLite `database/dev.sqlite` y documentos en `storage/framework/testing/dev-documents`. Ejecutar desde la raíz; también pueden configurarse rutas absolutas. Retirar variables institucionales heredadas de otra terminal antes de arrancar.

Abrir http://127.0.0.1:8000. El comando demo genera cuatro cuentas ficticias (administrador, editor, lector y lector suspendido), claves aleatorias mostradas una vez, un expediente y permiso temporal para el lector. No reemplaza cuentas existentes y solo funciona con esa base y carpeta aisladas. Cargar documentos ficticios desde la interfaz.

Alternativamente, sin datos demo:

```powershell
php artisan expedientes:admin nombre.usuario --name="Nombre completo"
```

Solicita una contraseña oculta de al menos 12 caracteres, letras y números. No hay contraseñas predeterminadas. En otra terminal con el mismo entorno:

```powershell
php artisan schedule:work
```

Los permisos vencidos también se bloquean en cada acceso sin planificador. Para desarrollar con MySQL, configurar una base exclusiva de pruebas en el archivo privado; el comando demo seguirá bloqueado fuera de su SQLite aislado.

## Identidad y permisos

El servidor selecciona el perfil mediante la variable existente `OIDC_ENABLED`; no hay selector para visitantes. Debe ser un booleano explícito. Configuración ausente, desconocida o contradictoria impide el arranque.

| Responsabilidad | Desarrollo local | Producción institucional |
| --- | --- | --- |
| Autenticación | Usuario y contraseña real, hash y límites de intentos | Keycloak OIDC, PKCE, state, nonce y firma RS256 |
| Identidad interna | ID de users | Mismo ID; vínculo único issuer + subject |
| Cuentas y credenciales | Administración local autorizada | Gestión exclusiva en plataforma |
| Roles/restricciones | ADMIN, LOADER, READER y suspensión | Los mismos, administrados en Expedientes |
| Acceso a la aplicación | Sin dependencia del portal | Comprobación central por petición protegida |

El administrador gestiona usuarios y permisos; el editor crea y administra sus expedientes; el lector accede a lo autorizado. Ser administrador del portal no concede ADMIN del sistema. Las identidades nuevas entran como lector, salvo un subject de bootstrap explícitamente autorizado.

En producción, el formulario de roles es separado, con identidad de consulta. Se rechazan rutas locales de creación de cuentas, login por contraseña y solicitudes para modificar credenciales/datos centrales. También se bloquean los comandos de administrador local y demo. El perfil local solo se permite en local/testing.

Las nuevas identidades institucionales tienen `password=NULL`; no se vinculan por correo, nombre o usuario. La migración conserva IDs, contraseñas anteriores, relaciones y datos. No se sincronizan usuarios entre instalaciones al publicar código.

## Producción y publicación

[deploy/production.env.example](deploy/production.env.example) es una plantilla sin secretos. Completar privadamente clave de aplicación, MySQL, cliente OIDC propio, issuer, callback, portal y credencial de API de acceso. Se requiere HTTPS, debug deshabilitado, sesiones database y cookie segura. Una caída de identidad/portal deniega acceso sin habilitar login local.

El logout borra la sesión local y solicita cierre institucional si tiene ID token. El backchannel verificado invalida las sesiones correspondientes. El cierre local sigue disponible durante fallas de la API central. La inactividad se limita con SESSION_LIFETIME y la revocación central se comprueba por petición protegida.

Flujo: desarrollo → revisión/pruebas → commit/tag identificado → paquete/imagen → respaldo → despliegue programado. Los comandos de construcción y recuperación están en [deploy/vm/README.md](deploy/vm/README.md). No copiar funciones manualmente entre ramas históricas. No desplegar esta reorganización ni su migración sobre producción sin una instrucción explícita.

## Verificación

```powershell
composer validate --no-check-publish
composer test
php artisan route:list --except-vendor
```

Composer test ejecuta PHPUnit directamente, con SQLite en memoria y su configuración de pruebas. Las respuestas controladas y claves RSA efímeras no sustituyen la aceptación institucional real. Véanse resultados y límites en docs/UNIFICACION.md.
