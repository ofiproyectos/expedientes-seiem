@php
    $estado = $expediente->status;
    $esResponsable = auth()->id() === $expediente->responsible_id;
    $esExcepcion = auth()->user()->isAdmin();
    $numero = 0;
    $siguiente = function () use (&$numero) { return str_pad((string) ++$numero, 2, '0', STR_PAD_LEFT); };
@endphp
<x-layouts.institucional :titulo="$expediente->name">
    <x-ui.migas :enlaces="[route('expedientes.index') => 'Expedientes']" :actual="$expediente->name"/>
    <div class="page-title">
        <div>
            <h1>{{ $expediente->name }}</h1>
            <p class="muted">{{ $can['edit'] ? 'Administra los datos, el estado y los documentos del expediente.' : ($can['share'] ? 'Consulta el expediente y administra sus permisos de lectura.' : 'Consulta del expediente y sus documentos autorizados.') }}</p>
        </div>
        <x-ui.boton variante="secondary" :href="route('expedientes.index')"><x-ui.icono nombre="flecha" class="icono-sm icono-invertido"/>Volver a expedientes</x-ui.boton>
    </div>

    <div class="selected-summary summary-four">
        <div><span>Folio</span><strong class="summary-folio">{{ $expediente->folio }}</strong><small class="muted">Creado el {{ $expediente->created_at->format('d/m/Y H:i') }}</small></div>
        <div><span>Tipo</span><strong>{{ $expediente->type }}</strong></div>
        <div><span>Responsable</span><strong>{{ $expediente->responsible->name }}</strong></div>
        <div><span>Estado</span><span class="status-badge {{ $estado->badge() }}">{{ $estado->label() }}</span></div>
    </div>

    @if(! $estado->isEditable() && ($esResponsable || $esExcepcion))
        <div class="notice" role="status"><x-ui.icono nombre="info"/><p>El expediente está <strong>{{ Str::lower($estado->label()) }}</strong>: queda protegido contra modificaciones de datos, cargas, versiones y bajas.{{ $estado->transitions() ? ' Para modificarlo, primero cambia su estado.' : '' }}</p></div>
    @endif

    {{-- Datos generales --}}
    <section class="form-section" aria-labelledby="seccion-datos">
        <div class="form-section-heading">
            <span class="section-number" aria-hidden="true">{{ $siguiente() }}</span>
            <div><h2 id="seccion-datos">Datos generales</h2><p>Identificación y descripción del expediente. El folio no se puede modificar.</p></div>
        </div>
        <dl class="data-list">
            <div><dt>Título</dt><dd>{{ $expediente->name }}</dd></div>
            <div><dt>Tipo de expediente</dt><dd>{{ $expediente->type }}</dd></div>
            <div><dt>Responsable</dt><dd>{{ $expediente->responsible->name }}</dd></div>
            <div><dt>Última modificación</dt><dd>{{ $expediente->updated_at->format('d/m/Y H:i') }}</dd></div>
            <div class="data-list-wide"><dt>Descripción</dt><dd>{{ $expediente->description ?: 'Sin descripción.' }}</dd></div>
        </dl>
        @if($can['edit'])
            <details class="disclosure disclosure-inset" @if($errors->hasAny(['name', 'type', 'description'])) open @endif>
                <summary><x-ui.icono nombre="editar" class="icono-sm"/>Editar datos</summary>
                <form method="POST" action="{{ route('expedientes.update', $expediente) }}" data-cargando="Guardando cambios…">
                    @csrf @method('PUT')
                    <p class="form-hint">Los campos marcados con <span class="required" aria-hidden="true">*</span> son obligatorios.</p>
                    <div class="form-grid two">
                        <div class="form-field">
                            <label for="editar-name">Título<span class="required" aria-hidden="true">*</span></label>
                            <input class="input-text" id="editar-name" name="name" value="{{ old('name', $expediente->name) }}" required aria-required="true" maxlength="255" @error('name') aria-invalid="true" aria-describedby="editar-name-error" @enderror>
                            <x-ui.error-campo campo="name" id="editar-name-error"/>
                        </div>
                        <div class="form-field">
                            <label for="editar-type">Tipo de expediente<span class="required" aria-hidden="true">*</span></label>
                            <input class="input-text" id="editar-type" name="type" value="{{ old('type', $expediente->type) }}" required aria-required="true" maxlength="100" @error('type') aria-invalid="true" aria-describedby="editar-type-error" @enderror>
                            <x-ui.error-campo campo="type" id="editar-type-error"/>
                        </div>
                    </div>
                    <div class="form-field">
                        <label for="editar-description">Descripción</label>
                        <textarea class="input-text" id="editar-description" name="description" maxlength="10000" @error('description') aria-invalid="true" aria-describedby="editar-description-error" @enderror>{{ old('description', $expediente->description) }}</textarea>
                        <x-ui.error-campo campo="description" id="editar-description-error"/>
                    </div>
                    <div class="form-actions">
                        <x-ui.boton type="submit" data-confirm="Se actualizarán los datos generales del expediente. El cambio queda registrado en la auditoría." data-confirm-title="Guardar cambios" data-confirm-accept="Sí, guardar"><x-ui.icono nombre="check" class="icono-sm"/>Guardar cambios</x-ui.boton>
                    </div>
                </form>
            </details>
        @endif
    </section>

    {{-- Estado --}}
    @if($can['status'] || $can['audit'])
        <section class="form-section" aria-labelledby="seccion-estado">
            <div class="form-section-heading">
                <span class="section-number" aria-hidden="true">{{ $siguiente() }}</span>
                <div><h2 id="seccion-estado">Estado</h2><p>Activo → Archivado. Los expedientes activos admiten modificaciones; el archivado es definitivo.</p></div>
            </div>
            @if($can['status'] && $estado->transitions())
                <form method="POST" action="{{ route('expedientes.status', $expediente) }}" data-cargando="Cambiando estado…">
                    @csrf
                    @if($esExcepcion)
                        <div class="notice"><x-ui.icono nombre="alerta"/><p>Como administrador, el cambio de estado es una <strong>excepción</strong>: requiere justificación y queda señalado en la auditoría.</p></div>
                    @endif
                    <div class="form-grid two">
                        <div class="form-field">
                            <label for="estado-nuevo">Nuevo estado<span class="required" aria-hidden="true">*</span></label>
                            <select class="input-text" id="estado-nuevo" name="status" required aria-required="true" @error('status') aria-invalid="true" aria-describedby="estado-nuevo-error" @enderror>
                                @foreach($estado->transitions() as $destino)<option value="{{ $destino->value }}">{{ $destino->actionLabel($estado) }} ({{ $destino->label() }})</option>@endforeach
                            </select>
                            <x-ui.error-campo campo="status" id="estado-nuevo-error"/>
                        </div>
                        <div class="form-field">
                            <label for="estado-reason">{{ $esExcepcion ? 'Justificación de la excepción' : 'Motivo (opcional)' }}@if($esExcepcion)<span class="required" aria-hidden="true">*</span>@endif</label>
                            <input class="input-text" id="estado-reason" name="reason" value="{{ old('reason') }}" maxlength="1000" @required($esExcepcion) @error('reason') aria-invalid="true" aria-describedby="estado-reason-error" @enderror>
                            <x-ui.error-campo campo="reason" id="estado-reason-error"/>
                        </div>
                    </div>
                    <div class="form-actions">
                        <x-ui.boton type="submit" data-confirm="El expediente se archivará y quedará protegido contra modificaciones. El archivado no se puede revertir." data-confirm-title="Archivar expediente" data-confirm-accept="Sí, archivar"><x-ui.icono nombre="check" class="icono-sm"/>Archivar expediente</x-ui.boton>
                    </div>
                </form>
            @elseif(! $estado->transitions())
                <p class="muted">El expediente está archivado; no admite más cambios de estado.</p>
            @endif
            <h3 class="subsection-title">Historial de estados</h3>
            @if($statusHistory->isEmpty())
                <p class="muted">Sin cambios de estado registrados. Estado actual: {{ $estado->label() }}.</p>
            @else
                <div class="table-scroll" role="region" aria-label="Historial de estados" tabindex="0">
                    <table class="data-table">
                        <thead><tr><th scope="col">Fecha</th><th scope="col">Cambio</th><th scope="col">Usuario</th><th scope="col">Motivo</th></tr></thead>
                        <tbody>
                            @foreach($statusHistory as $evento)
                                <tr>
                                    <td class="cell-nowrap">{{ $evento->created_at->format('d/m/Y H:i') }}</td>
                                    <td>{{ $evento->description }}@if($evento->result === 'denied')<span class="status-badge status-rechazada">Rechazado</span>@endif</td>
                                    <td>{{ $evento->user?->name ?? $evento->username }}</td>
                                    <td>{{ $evento->metadata['reason'] ?? '—' }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </section>
    @endif

    {{-- Documentos --}}
    <section class="form-section" aria-labelledby="seccion-documentos">
        <div class="form-section-heading">
            <span class="section-number" aria-hidden="true">{{ $siguiente() }}</span>
            <div><h2 id="seccion-documentos">Documentos</h2><p>{{ $can['history'] ? 'Cada documento conserva su historial de versiones, autor, fecha y hora.' : 'Se muestra la versión vigente de cada documento autorizado.' }}</p></div>
        </div>
        @if($can['edit'])
            <details class="disclosure disclosure-inset" @if($errors->hasAny(['files', 'files.*'])) open @endif>
                <summary><x-ui.icono nombre="subir" class="icono-sm"/>Cargar documentos</summary>
                <form method="POST" enctype="multipart/form-data" action="{{ route('documents.store', $expediente) }}" data-cargando="Cargando documentos…">
                    @csrf
                    <div class="form-grid two">
                        <div class="form-field">
                            <label for="documento-files">Archivos<span class="required" aria-hidden="true">*</span></label>
                            <input class="input-text" id="documento-files" name="files[]" type="file" multiple accept=".pdf,.docx,.xlsx,.jpg,.jpeg,.png" required aria-required="true" aria-describedby="documento-files-hint @error('files') documento-files-error @enderror" @error('files') aria-invalid="true" @enderror>
                            <small id="documento-files-hint" class="form-hint">Uno o varios archivos PDF, DOCX, XLSX, JPG o PNG; máximo 10 MB cada uno. Si alguno no es válido, no se guarda ninguno.</small>
                            <x-ui.error-campo campo="files" id="documento-files-error"/>
                        </div>
                        <div class="form-field">
                            <label for="documento-type">Tipo de documento<span class="required" aria-hidden="true">*</span></label>
                            <select class="input-text" id="documento-type" name="type" required aria-required="true">
                                @foreach(config('expedientes.document_types') as $type)<option @selected(old('type') === $type)>{{ $type }}</option>@endforeach
                            </select>
                        </div>
                        <div class="form-field">
                            <label for="documento-title">Título</label>
                            <input class="input-text" id="documento-title" name="title" value="{{ old('title') }}" maxlength="255" aria-describedby="documento-title-hint">
                            <small id="documento-title-hint" class="form-hint">Solo al cargar un archivo. Si se omite o se cargan varios, se usa el nombre de cada archivo.</small>
                        </div>
                        <div class="form-field">
                            <label for="documento-description">Descripción</label>
                            <input class="input-text" id="documento-description" name="description" value="{{ old('description') }}" maxlength="2000">
                        </div>
                    </div>
                    <div class="form-actions"><x-ui.boton type="submit"><x-ui.icono nombre="subir" class="icono-sm"/>Cargar documentos</x-ui.boton></div>
                </form>
            </details>
        @endif
        <div>
            @forelse($expediente->documents as $document)
                @include('expedientes.document', ['editable' => $can['edit'], 'history' => $can['history']])
            @empty
                <div class="empty-state">
                    <x-ui.icono nombre="documento"/>
                    <h3>Aún no hay documentos</h3>
                    <p>{{ $can['edit'] ? 'Usa “Cargar documentos” para agregar el primero.' : 'Los documentos aparecerán aquí cuando se carguen.' }}</p>
                </div>
            @endforelse
        </div>
        @if($retired->isNotEmpty())
            <details class="disclosure disclosure-inset">
                <summary>Documentos dados de baja ({{ $retired->count() }})</summary>
                <div class="table-scroll" role="region" aria-label="Documentos dados de baja" tabindex="0">
                    <table class="data-table">
                        <thead><tr><th scope="col">Documento</th><th scope="col">Versiones</th><th scope="col">Baja</th><th scope="col">Responsable</th><th scope="col">Motivo</th></tr></thead>
                        <tbody>
                            @foreach($retired as $doc)
                                <tr>
                                    <td><strong>{{ $doc->name }}</strong><small>{{ $doc->type }}</small></td>
                                    <td>{{ $doc->latestVersion?->version ?? 0 }}</td>
                                    <td class="cell-nowrap">{{ $doc->deleted_at->format('d/m/Y H:i') }}</td>
                                    <td>{{ $doc->retiredBy?->name }}</td>
                                    <td>{{ $doc->deletion_reason }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </details>
        @endif
    </section>

    {{-- Permisos de lectura (solo ADMIN) --}}
    @if($can['share'])
        <section class="form-section" aria-labelledby="seccion-accesos">
            <div class="form-section-heading">
                <span class="section-number" aria-hidden="true">{{ $siguiente() }}</span>
                <div><h2 id="seccion-accesos">Permisos de lectura</h2><p>Solo el administrador concede o revoca. La descarga es una autorización adicional a la lectura.</p></div>
            </div>
            <form method="POST" action="{{ route('grants.store', $expediente) }}" data-cargando="Asignando permiso…">
                @csrf
                <div class="form-grid">
                    <div class="form-field">
                        <label for="acceso-user">Usuario lector<span class="required" aria-hidden="true">*</span></label>
                        <select class="input-text" id="acceso-user" name="user_id" required aria-required="true" @error('user_id') aria-invalid="true" aria-describedby="acceso-user-error" @enderror>
                            @foreach($readers as $user)<option value="{{ $user->id }}" @selected((string) old('user_id') === (string) $user->id)>{{ $user->name }} · {{ $user->username }}</option>@endforeach
                        </select>
                        <x-ui.error-campo campo="user_id" id="acceso-user-error"/>
                    </div>
                    <div class="form-field">
                        <label for="acceso-document">Alcance</label>
                        <select class="input-text" id="acceso-document" name="document_id" @error('document_id') aria-invalid="true" aria-describedby="acceso-document-error" @enderror>
                            <option value="">Expediente completo</option>
                            @foreach($expediente->documents as $doc)<option value="{{ $doc->id }}" @selected((string) old('document_id') === (string) $doc->id)>{{ $doc->name }}</option>@endforeach
                        </select>
                        <x-ui.error-campo campo="document_id" id="acceso-document-error"/>
                    </div>
                    <div class="form-field">
                        <label for="acceso-starts">Inicio<span class="required" aria-hidden="true">*</span></label>
                        <input class="input-text" id="acceso-starts" type="datetime-local" name="starts_at" value="{{ old('starts_at', now()->format('Y-m-d\TH:i')) }}" required aria-required="true" @error('starts_at') aria-invalid="true" aria-describedby="acceso-starts-error" @enderror>
                        <x-ui.error-campo campo="starts_at" id="acceso-starts-error"/>
                    </div>
                    <div class="form-field">
                        <label for="acceso-expires">Expiración (opcional)</label>
                        <input class="input-text" id="acceso-expires" type="datetime-local" name="expires_at" value="{{ old('expires_at') }}" @error('expires_at') aria-invalid="true" aria-describedby="acceso-expires-error" @enderror>
                        <x-ui.error-campo campo="expires_at" id="acceso-expires-error"/>
                    </div>
                </div>
                <label class="checkbox-label"><input type="checkbox" name="can_download" value="1" @checked(old('can_download'))>Permitir también la descarga</label>
                <div class="form-actions">
                    <x-ui.boton type="submit"><x-ui.icono nombre="agregar" class="icono-sm"/>Asignar permiso</x-ui.boton>
                </div>
            </form>
            @include('partials.grants-table', ['grants' => $grants, 'mostrarExpediente' => false, 'revocable' => true])
        </section>
    @endif

    {{-- Historial del expediente (solo ADMIN) --}}
    @if($can['audit'])
        <section class="form-section" aria-labelledby="seccion-historial">
            <div class="form-section-heading">
                <span class="section-number" aria-hidden="true">{{ $siguiente() }}</span>
                <div><h2 id="seccion-historial">Historial del expediente</h2><p>Cargas, cambios, versiones, consultas y permisos, del más reciente al más antiguo. No se puede modificar.</p></div>
            </div>
            @include('partials.audit-table', ['events' => $history, 'mostrarRecurso' => false])
            <p class="form-actions"><a class="text-link" href="{{ route('audit.index', ['expediente_id' => $expediente->id]) }}"><x-ui.icono nombre="buscar" class="icono-sm"/>Consultar en Auditoría</a></p>
        </section>
    @endif
</x-layouts.institucional>
