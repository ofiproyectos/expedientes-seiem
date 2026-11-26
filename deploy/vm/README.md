# Publicación institucional de Expedientes

La instalación observada está en https://192.168.100.54:10443, fuente /opt/seiem/expedientes, configuración privada /opt/seiem/env/expedientes.env, MySQL 8.4 y almacenamiento persistente. Infraestructura mantiene Compose, Nginx, TLS, Keycloak, API de acceso, respaldos y planificador. No publicar/desplegar sin instrucción explícita.

## Construcción

Desde un checkout revisado de unificacion, después de confirmar cambios:

```powershell
composer validate --no-check-publish
composer test
$revision = git rev-parse HEAD
powershell -NoProfile -ExecutionPolicy Bypass -File deploy/vm/package.ps1 -Ref $revision -Destination "$env:TEMP\seiem-expedientes-source.tar.gz"
docker build -f deploy/vm/Dockerfile --build-arg "SOURCE_REVISION=$revision" -t "expedientes-seiem:$revision" .
```

El paquete usa exclusivamente el commit elegido y genera manifiesto de revisión/SHA256. Rechaza cambios sin confirmar y excluye secretos, bases, documentos, dependencias instaladas y cachés. Ya no requiere deploy/vm/composer-cache. Composer usa el lock y las imágenes base están fijadas por digest. Construir con conectividad o repositorio administrado de artefactos. Conservar/promover la misma imagen por digest: Alpine aún impide prometer reconstrucción idéntica sin fijar un snapshot de sus paquetes.

Completar privadamente [../production.env.example](../production.env.example). No pasar secretos como argumentos de construcción. Registrar cliente propio expedientes-seiem, callback /auth/callback, logout, backchannel /auth/backchannel-logout, CA y asignación central. Bootstrap ADMIN requiere subject explícitamente aprobado; el resto entra como READER. Retirar bootstrap tras verificar el administrador.

## Actualización autorizada

Ensayar en integración y respaldar MySQL, configuración y todo storage/app, con restauración comprobada. Registrar revisión y digest anterior/nuevo. No importar desarrollo ni extraer archivos sobre secretos o volúmenes.

En /opt/seiem, preparada la fuente identificada y autorizada la actualización:

```sh
docker compose build --build-arg SOURCE_REVISION=REVISION_GIT expedientes
docker compose up -d --wait --no-deps expedientes
docker compose exec -T expedientes php artisan migrate:status
```

Sustituir REVISION_GIT por el hash del manifiesto. Preferir distribuir la imagen ya ensayada y fijar su digest en Compose. Solo actualizar portal/proxy/identidad si cambian sus contratos. Esta imagen sirve PHP-FPM; el proxy TLS sigue en infraestructura. Mantener volumen storage, CA y configuración privada fuera de la imagen.

El entrypoint valida perfil antes de migrar y prepara cachés. La nueva migración no se ha ejecutado en producción; ensayarla en MySQL de integración. Serializar despliegues para evitar migraciones concurrentes. Mantener schedule:run cada minuto. No usar docker compose down -v.

Aceptar con cuentas temporales en integración: SSO, sin asignación, revocación, suspensión interna, roles, rutas locales bloqueadas, documentos ficticios y logout institucional. Fallas del portal/Keycloak deben denegar acceso sin habilitar otra autenticación.

## Recuperación

Conservar digest anterior, manifiesto y respaldo. Si el esquema sigue compatible, recuperar imagen anterior y verificar acceso/salud. Si no, planificar restauración conjunta de base/archivos/configuración, contabilizando cambios posteriores al respaldo. No hacer migrate:rollback a ciegas: NOT NULL se rechaza mientras haya identidades sin contraseña.

Las utilidades históricas setup-expedientes.py, enable-expedientes.php, expedientes-scheduler.py, verify-expedientes-backup.py y pruebas browser institucionales pertenecen al repositorio de la plataforma. Verificar allí su disponibilidad/compatibilidad; no son dependencias del desarrollo autónomo.
