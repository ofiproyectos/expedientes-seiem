# Diagnóstico y entrega de unificación

Revisión: 26 de noviembre de 2026. Repositorio: ofiproyectos/expedientes-seiem.

## Estado real y preservación

GitHub tenía únicamente main, en 117c8e9314e9dfe572d38a835ebad5e2904e5800. Localmente main y Plataforma apuntaban a ese commit. Había cambios sin confirmar en 17 archivos y nuevos componentes institucionales sin seguimiento. Las funciones del sistema ya compartían una implementación; no se encontraron dos proyectos de negocio que fusionar.

Se accedió por SSH autorizado a 192.168.100.54 como alfonso. Se compararon contenidos normalizando solo finales de línea: aplicación, configuración, migraciones, vistas, recursos públicos, rutas, idiomas, arranque y dependencias bloqueadas. La fuente en /opt/seiem/expedientes y en el contenedor activo seiem-expedientes-1 coincidía con el árbol local anterior a estos cambios. Los archivos de despliegue coincidían con el host. Se excluyeron secretos, cachés, documentos y datos del cotejo de fuentes.

El contenedor estaba saludable, con volumen seiem_expedientes_storage y CA montada en solo lectura. No se ejecutaron migraciones, cambios de cuentas, importaciones, reinicios ni modificaciones en el servidor.

Main queda intacta. Se conservan tres ramas además de ella:

| Rama | Propósito |
| --- | --- |
| independiente | Referencia anterior a integración: 117c8e9 |
| Plataforma | Fuente institucional observada en host y contenedor antes de unificar |
| unificacion | Fuente única para desarrollo futuro |

Plataforma incorpora el código que estaba sin confirmar y la integración existente. Las ramas históricas no se mantienen como productos paralelos ni certifican nuevos despliegues.

El SQLite heredado llamado expedientes estaba versionado: se retira solo del índice de unificacion. Se preservan archivo local e historia. No se borran bases ni documentos. Las ramas históricas conservan su árbol original, incluido ese archivo legado, y no deben usarse para distribuir datos.

## Cambios y estructura final

- Services/Identity/LaravelOidcClient adapta OIDC a la sesión Laravel: PKCE S256, firma RS256, issuer exacto, audiencia, nonce, vigencia y authorized party. Rechaza algoritmos alternativos y cabeceras de claves no autorizadas.
- InstitutionalAuthController coordina login, callback, vínculo issuer/subject y logout/backchannel. No adopta cuentas por nombre, usuario o correo.
- PlatformAccess y VerifyPlatformAccess comprueban asignación institucional separada de los permisos internos, también para archivos/visor. Una caída o revocación no habilita acceso alternativo.
- IdentityProfile y AppServiceProvider validan al arrancar. OIDC_ENABLED sigue como único selector. Local solo en local/testing, sin configuración institucional activa. Institucional exige HTTPS y credenciales; fuera de pruebas requiere debug deshabilitado, sesiones database y cookie segura.
- UserController separa las acciones de cuentas locales de la edición institucional de roles/restricciones. user-form y users/roles son formularios diferentes. Las solicitudes directas a altas o credenciales locales se rechazan en perfil institucional.
- Users conserva IDs y relaciones. identity_issuer + identity_subject tiene índice único. Password nullable representa ausencia de credencial local sin dos bases obligatorias. Identity_sessions relaciona sesiones locales con sid del proveedor.
- Políticas, modelos, expedientes, cargas, versiones, permisos individuales/por lote, descargas y auditoría siguen compartidos. No se crearon microservicios.
- Expedientes:demo genera usuarios ficticios y claves aleatorias solo en SQLite/carpeta aislados; no reemplaza cuentas. Expedientes:admin respeta el perfil.
- Empaquetado desde commit Git, manifiesto SHA256, composer.lock, imágenes base por digest y etiqueta de revisión. El Dockerfile ya no depende de una carpeta manual de ZIP ni instala PostgreSQL innecesario.

Los módulos de negocio y autorización internos son funcionales. Los datos demo son ficticios. Keycloak y la API de acceso son indispensables para aceptación institucional; las pruebas autónomas usan respuestas controladas y claves efímeras.

## Migración y conservación

Se mantienen las migraciones anteriores y se añade 2026_11_26_000001_allow_identity_without_local_password. Solo cambia nulabilidad de users.password; no transforma contraseñas, enlaza cuentas existentes ni altera IDs/permisos. Las nuevas identidades SSO no tienen contraseña local.

El rollback rechaza restaurar NOT NULL si hay identidades sin contraseña. Revertir en ese caso exige un procedimiento planificado o restauración; no inventar credenciales. La migración está probada en SQLite y pendiente en un clon MySQL 8.4.

## Desarrollo y operaciones

Los comandos exactos están en README.md. El desarrollo mínimo usa PHP y SQLite sin portal, Keycloak, MySQL ni correo. Cada instalación conserva base, documentos y secretos propios. La actualización de código no sincroniza datos.

Compose, proxy, API central, cliente Keycloak, TLS, respaldo y planificador pertenecen a infraestructura. Las referencias históricas a scripts del portal no significan que estén incluidos en este repositorio.

## Futuro desacoplamiento de producción

No está implementada ni certificada una producción independiente; el perfil local está bloqueado fuera de desarrollo/pruebas. Se requiere:

1. Definir autenticación independiente de producción y sus controles operativos.
2. Seleccionar cuentas y preparar credenciales administrativamente; no reutilizar contraseñas de Keycloak ni convertir subjects/tokens en claves.
3. Garantizar administrador autorizado y conservar IDs, relaciones, datos, roles y restricciones.
4. Resolver las vinculaciones issuer/subject y posibles duplicados mediante revisión explícita.
5. Sustituir la asignación institucional por una política independiente aprobada.
6. Invalidar sesiones y revisar cookies, logout, dominios, TLS, monitoreo y recuperación.
7. Respaldar, ensayar la transición aislada, verificarla y aprobar el despliegue.

Cambiar una variable no hace utilizables las contraseñas institucionales en este sistema.

## Pruebas y limitaciones

Estado inicial: 64 pruebas y 636 aserciones correctas. Tras los cambios: 77 pruebas y 679 aserciones correctas sobre SQLite en memoria. Cubren login local real, permisos/documentos, vigencia, callback falsificado/vencido, revocación central, indisponibilidad del portal, ausencia de enlace por nombre, bootstrap explícito, suspensión, edición de roles, bloqueo de altas/contraseñas locales, logout durante fallas, backchannel y configuración inválida. Se verificó firma RSA real con clave efímera y rechazo de payload manipulado, issuer, audiencia, nonce, vencimiento y authorized party inválidos.

Prueba HTTP autónoma adicional: migraciones desde cero en database/dev.sqlite, datos demo y servidor PHP con sesiones reales. Los cuatro usuarios ficticios comprobaron login, suspensión, consulta de expediente y gestión de usuarios únicamente por ADMIN. No se levantó portal/Keycloak ni se cambió el .env privado. Las claves generadas se conservaron solo dentro de metadatos locales .git, fuera del árbol versionado. Las bases de pruebas permanecen ignoradas.

Composer validate pasó. La comprobación final revisa Git y el paquete de fuentes. PHPUnit fuerza SQLite en memoria y configuración aislada aunque la terminal herede otra conexión; el bootstrap rechaza una caché de configuración de pruebas antes de iniciar proveedores o migraciones. No se construyó la imagen: Docker no está instalado en este equipo. Los digests base y composer.lock fijan parte de las entradas; Alpine aún requiere un snapshot de paquetes para reconstrucción idéntica byte por byte. Para repetir un despliegue, promover la misma imagen por digest.

Antes de desplegar: construir imagen; probar migración/aceptación en MySQL aislado; ejecutar SSO real en integración con Keycloak/API central (expiración, revocación, cierre y separación entre administrador de plataforma e interno); verificar respaldo/restauración y publicar imagen identificada. La inspección SSH del despliegue anterior no certifica los cambios nuevos.

Se intentó publicar ramas, pero SSH/HTTPS no tienen autenticación de escritura y el conector devuelve HTTP 403 al crear un árbol Git. Se conservan ramas/commits locales; no se declara publicación remota.
