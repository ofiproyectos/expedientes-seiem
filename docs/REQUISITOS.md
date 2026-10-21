# Expedientes: requerimientos y trazabilidad

La aplicación usa Laravel 13, PHP 8.4 y MySQL. Las pantallas están construidas con Blade y CSS local (sistema visual SEIEM), sin requerir compilación de JavaScript.

## Roles

| Rol | Etiqueta | Puede |
|---|---|---|
| ADMIN | Administrador | Administrar usuarios y roles; conceder y revocar permisos de lectura y descarga; consultar todos los expedientes, sus versiones y la auditoría. **No** crea ni edita expedientes ni carga documentos. Cambia estados solo por **excepción**, con justificación obligatoria que queda marcada en la auditoría. |
| LOADER | Cargador | Crear expedientes (queda como responsable) y, sobre los suyos activos: editar metadatos, cargar documentos y versiones, clasificar, dar de baja y restaurar versiones; archivarlos. No asigna permisos. |
| READER | Lector | Consultar únicamente lo que el ADMIN le comparte (expediente completo o documento), solo en su versión vigente; descargar si el permiso lo autoriza. |

Un cambio de rol o la desactivación aplican en la siguiente petición: el rol se lee de la base en cada solicitud, la desactivación cierra las sesiones y el middleware rechaza cuentas inactivas. Un usuario reactivado conserva solo los permisos que sigan vigentes.

## Decisiones

- **Acceso:** nombre de usuario único (minúsculas, letras, números, `.`, `-`, `_`) y contraseña; sin correo. No hay recuperación por correo: el ADMIN restablece la contraseña (restablecimiento administrativo de RF-AUT-005) y cada usuario puede cambiar la suya con la contraseña actual. Cinco intentos fallidos bloquean 15 minutos la combinación usuario + IP.
- **Estados del expediente:** Activo → Archivado; Archivado es final. Solo Activo admite modificaciones. Al crearse queda en Activo. La migración convierte los borradores existentes a ACTIVO y los cerrados a ARCHIVADO; conserva los activos y archivados existentes.
- **Folio:** UUID generado; ni el folio ni el identificador interno se modifican.
- **Documentos:** uno o varios archivos por envío (máximo 20); todo el lote se acepta o se rechaza. Tipo del catálogo `config/expedientes.php`; título (por defecto, el nombre del archivo) y descripción opcional, editables después.
- **Duplicados:** se detectan por SHA-256 en el historial completo del expediente, incluidos documentos dados de baja. La **restauración** de una versión es la excepción: copia el archivo como versión nueva marcada «Restaurado desde la versión N».
- **Baja lógica:** el documento desaparece de las consultas ordinarias y del acceso de lectores; se conservan versiones, responsable, fecha y motivo, visibles para ADMIN y el LOADER responsable.
- **Versiones:** cada una conserva su archivo; la de número mayor es la vigente y se marca como tal. El historial completo solo lo ven ADMIN y el LOADER responsable.
- **Permisos:** sobre expediente completo o documento individual, con inicio, expiración opcional y descarga independiente. La vigencia se evalúa en cada petición; el planificador marca los vencidos como revocados.
- **Visor:** PDF e imágenes en un visor integrado; DOCX y XLSX muestran un mensaje y requieren descarga autorizada.
- **Auditoría:** tabla `audit_events` de solo inserción (el modelo impide modificar o eliminar). Registra fecha, acción, resultado, usuario, recurso, expediente, IP y metadatos sin contraseñas, tokens ni contenido documental. Todas las respuestas 403 se registran como «Acceso denegado».
- **Sesión:** cookie HttpOnly y SameSite=Lax; segura (solo HTTPS) cuando `APP_ENV=production` o `SESSION_SECURE_COOKIE=true`; el identificador se regenera al iniciar sesión y nunca viaja en la URL.

## Trazabilidad

Pruebas en `tests/Feature/RequirementsTest.php` (R) y `tests/Feature/AccessControlTest.php` (A).

| Requerimiento | Implementación | Prueba |
|---|---|---|
| RF-AUT-001 Inicio de sesión | `AuthController::login` | R `login_and_logout_are_audited…`, A `login_*` |
| RF-AUT-002 Cierre de sesión | `AuthController::logout` | R `login_and_logout…`, A `login_updates…` |
| RF-AUT-003 Control por rol | `ExpedientePolicy`, `DocumentPolicy`, `bootstrap/app.php` | R `loader_cannot_assign…`, A `reader_can_view_but_cannot…` |
| RF-AUT-004 Sesiones | `config/session.php`, `EnsureActiveUser`, `UserController::save` | R `session_cookie…`, A `inactive_user…` |
| RF-AUT-005 Cambio de contraseña propia | Retirado del alcance por solicitud del usuario; sin página ni rutas | R `removed_password_change_page_and_endpoint_are_not_available` |
| RF-USR-001/002/003/004 | `UserController::save` | R `user_registration_and_role_changes…`, A `admin_manages_users…` |
| RF-USR-005 Consulta de usuarios | `UserController::index` | R `users_can_be_searched…` |
| RF-EXP-001/002/003 | `ExpedienteController::store/update/show` | R `only_loader_creates…`, A `closed_and_archived…` |
| RF-EXP-004 Estados | `ExpedienteStatus`, `ExpedienteController::changeStatus` | R `status_machine…` |
| RF-DOC-001/002 Carga y clasificación | `DocumentController::store/update` | R `multiple_files_upload…`, A `owner_uploads_versions…` |
| RF-DOC-003 Metadatos | `expedientes/document.blade.php` | R `versions_history…` |
| RF-DOC-004 Baja lógica | `DocumentController::retire` | R `document_retirement…` |
| RF-PRM-001/002/003/004 | `AccessGrantController`, `partials/grants-table` | R `permissions_report_grantor…`, A `admin_grants_and_revokes…`, `expired_future…` |
| RF-VIS-001/002 | `DocumentController::viewer/file` | R `viewer_shows_pdf…` |
| RF-BUS-001/002/003 | `ExpedienteController::index`, `Support\Listing` | R `search_filters_sorting…` |
| RF-AUD-001/002/003 | `Support\Audit`, `AuditEvent`, `AuditController`, sección «Historial del expediente» | R `audit_is_admin_only…` |
| RF-VER-001/002/003 | `DocumentController::replace/restore` | R `versions_history_and_restoration`, A `owner_uploads_versions…` |

## Pendientes y límites

- Si un LOADER se desactiva, sus expedientes quedan sin responsable activo: no existe reasignación porque el ADMIN no edita expedientes. Definir el procedimiento si se requiere.
- La restricción de descarga es funcional: no impide capturar o copiar lo que el usuario puede visualizar.
- La validación de tipo no sustituye un antivirus. Para producción: HTTPS, respaldos probados, operación del planificador (`schedule:work` o cron), revisión de seguridad y política de retención de la auditoría.
- La auditoría no tiene firma criptográfica: es inalterable desde la aplicación, no frente a un administrador de base de datos.
