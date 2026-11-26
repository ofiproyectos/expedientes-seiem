# Expedientes en la plataforma institucional

La copia está en https://192.168.100.54:10443 y aparece en Departamento de Personal como Sistema de Expedientes. El código se modifica en este repositorio y se despliega a `/opt/seiem/expedientes`. La configuración privada está fuera del repositorio, en `/opt/seiem/env/expedientes.env`.

La instalación parte de una base MySQL propia, con almacenamiento privado persistente, clave de aplicación y cookie independientes. No se importó la base MySQL local: su conexión no estaba disponible desde la sesión de despliegue. El SQLite antiguo incluido como archivo `expedientes` se abrió únicamente en modo lectura y tenía cero usuarios, expedientes, documentos, versiones y permisos. El sistema nuevo queda sin expedientes previos. Los archivos se sirven mediante rutas autorizadas; la base y su almacenamiento no son públicos.

Con `OIDC_ENABLED=true`, el acceso institucional usa Authorization Code con PKCE S256, RS256, state y nonce. Se bloquea la contraseña local. Toda petición protegida, incluidas descargas y visor, comprueba el permiso central. Retirar el acceso en la plataforma bloquea la petición siguiente; un fallo de verificación se rechaza con 503. El cierre institucional está en el menú de cuenta y recibe notificaciones backchannel.

La plataforma concede acceso al sistema. Cada identidad nueva entra como Lector, sin expedientes autorizados. La cuenta bootstrap explícita recibe el primer rol Administrador; no se adopta una cuenta por coincidencia de nombre, usuario o correo. En Usuarios y permisos se administran los roles y la desactivación local; nombres y contraseñas se administran en la plataforma. El Administrador de Expedientes gestiona usuarios y permisos; el Editor crea y administra sus propios expedientes; el Lector consulta y descarga lo que le hayan autorizado. Administrar la plataforma no concede automáticamente administración del sistema.

## Despliegue

1. Instalar las dependencias de `composer.lock`. El Dockerfile usa PHP 8.4 con PDO MySQL, cURL, Intl y ZIP. La base utiliza MySQL 8.4.
2. Preparar `deploy/vm/composer-cache` con los ZIP de producción para el piloto con conectividad limitada. Se excluye de Git y no contiene secretos.
3. Ejecutar `powershell -NoProfile -ExecutionPolicy Bypass -File deploy/vm/package.ps1`, copiar el paquete y extraerlo en `/opt/seiem/expedientes`. Se excluyen `.env`, datos, dependencias instaladas y cachés.
4. El Compose y Nginx del proyecto de la plataforma declaran `expedientes`, `expedientes-db`, su red y volúmenes y el puerto HTTPS 10443. `setup-expedientes.py` genera secretos privados y registra el cliente `expedientes-seiem` y un token exclusivo para verificar permisos; al repetirlo conserva los secretos.
5. Construir y levantar con `docker compose build expedientes portal` y `docker compose up -d --wait expedientes portal proxy`. Para reutilizar las capas de este piloto se usa `--build-arg APK_MIRROR=http://127.0.0.1:8899`; puede omitirse con acceso normal a Alpine.
6. Habilitar al administrador inicial con `docker compose exec -T portal php < app/deploy/vm/enable-expedientes.php` e instalar el planificador con `expedientes-scheduler.py`. No se vuelven a importar bases en actualizaciones normales.

El contenedor migra y prepara cachés al iniciar. El límite es de 10 MB por archivo y 20 archivos por carga; PHP y Nginx permiten ese tamaño de lote. La tarea de expiración corre cada minuto; los permisos también se verifican al acceder.

El respaldo diario incluye MySQL, configuración y `storage/app`, con documentos y versiones. `verify-expedientes-backup.py` restaura en una base temporal aislada, verifica los conteos y elimina únicamente esa base temporal. No usar `docker compose down -v`: elimina volúmenes persistentes.

## Verificación

Las pruebas locales usan SQLite en memoria y no modifican la base local. `tests/Browser/expedientes.mjs`, en el proyecto de la plataforma, verifica SSO, roles, creación, carga y descarga, permisos por lote, revocación y suspensión. Usa cuentas y archivos desechables que elimina al terminar. No se crean contraseñas predeterminadas ni se guardan credenciales en Git.
