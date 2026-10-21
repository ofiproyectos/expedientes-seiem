@php
    // Índice del expediente (manual §8.3.1): mismas secciones, en el mismo orden, que el contenido visible para el rol.
    $secciones = array_filter([
        'seccion-datos' => 'Datos generales',
        'seccion-documentos' => 'Documentos',
        'seccion-accesos' => $can['share'] ? 'Permisos de lectura' : null,
    ]);
    $numeros = [];
    foreach (array_keys($secciones) as $i => $id) { $numeros[$id] = str_pad((string) ($i + 1), 2, '0', STR_PAD_LEFT); }
    // Con menos de tres secciones el índice no orienta: el contenido usa todo el ancho.
    $conIndice = count($secciones) >= 3;
    $conError = fn (string $dialogo, string $campo) => old('_dialogo') === $dialogo && $errors->has($campo);
    $valor = fn (string $dialogo, string $campo, $actual) => old('_dialogo') === $dialogo ? old($campo) : $actual;
@endphp
<x-layouts.institucional :titulo="$expediente->name">
    <x-ui.migas :enlaces="[route('expedientes.index') => 'Expedientes']" :actual="$expediente->name"/>
    <div class="page-title">
        <div>
            <h1>{{ $expediente->name }}</h1>
            <p class="muted">{{ $can['edit'] ? 'Consulta y administra los datos y los documentos del expediente.' : ($can['share'] ? 'Consulta el expediente y administra sus permisos de lectura.' : 'Consulta del expediente y sus documentos autorizados.') }}</p>
        </div>
        <x-ui.boton variante="secondary" :href="route('expedientes.index')"><x-ui.icono nombre="flecha" class="icono-sm icono-invertido"/>Volver a expedientes</x-ui.boton>
    </div>

    <div class="selected-summary summary-four">
        <div><span>Folio</span><strong class="summary-folio">{{ $expediente->folio }}</strong></div>
        <div><span>Responsable</span><strong>{{ $expediente->responsible->name }}</strong></div>
        <div><span>Creación</span><strong>{{ $expediente->created_at->format('d/m/Y H:i') }}</strong></div>
        <div><span>Última modificación</span><strong>{{ $expediente->updated_at->format('d/m/Y H:i') }}</strong></div>
    </div>

    <div @class(['form-layout' => $conIndice])>
        @if($conIndice)
            <aside class="section-sidebar">
                <nav class="section-nav" aria-label="Secciones del expediente" data-section-nav>
                    <p class="nav-caption">Contenido</p>
                    @foreach($secciones as $id => $titulo)
                        <a href="#{{ $id }}"><span class="nav-number">{{ $numeros[$id] }}</span>{{ $titulo }}</a>
                    @endforeach
                </nav>
            </aside>
        @endif
        <div class="review-panel">
            {{-- Datos generales: consulta; «Editar datos» abre un diálogo. --}}
            <section class="form-section" aria-labelledby="seccion-datos">
                <div class="form-section-heading">
                    <span class="section-number" aria-hidden="true">{{ $numeros['seccion-datos'] }}</span>
                    <div><h2 id="seccion-datos">Datos generales</h2><p>Descripción del expediente. El folio lo asigna el sistema y no se modifica.</p></div>
                    @if($can['edit'])
                        <div class="section-actions">
                            <x-ui.boton variante="secondary" data-dialogo-abrir="editar-datos"><x-ui.icono nombre="editar" class="icono-sm"/>Editar datos</x-ui.boton>
                        </div>
                    @endif
                </div>
                <dl class="data-list">
                    <div class="data-list-wide"><dt>Descripción</dt><dd @class(['muted' => ! $expediente->description])>{{ $expediente->description ?: 'Sin descripción.' }}</dd></div>
                </dl>
            </section>

            {{-- Documentos --}}
            <section class="form-section" aria-labelledby="seccion-documentos">
                <div class="form-section-heading">
                    <span class="section-number" aria-hidden="true">{{ $numeros['seccion-documentos'] }}</span>
                    <div><h2 id="seccion-documentos">Documentos</h2><p>{{ $can['history'] ? 'Versión vigente de cada documento. Sus operaciones e historial están en el botón de acciones de cada fila.' : 'Versión vigente de cada documento autorizado.' }}</p></div>
                    @if($can['edit'])
                        <div class="section-actions">
                            <x-ui.boton data-dialogo-abrir="cargar-documentos"><x-ui.icono nombre="subir" class="icono-sm"/>Cargar documentos</x-ui.boton>
                        </div>
                    @endif
                </div>
                @if($expediente->documents->isEmpty())
                    <div class="empty-state">
                        <x-ui.icono nombre="documento"/>
                        <h3>Aún no hay documentos</h3>
                        <p>{{ $can['edit'] ? 'Usa «Cargar documentos» para agregar el primero.' : 'Los documentos aparecerán aquí cuando se carguen.' }}</p>
                    </div>
                @else
                    @include('expedientes.documents-table', ['documents' => $expediente->documents, 'editable' => $can['edit'], 'history' => $can['history'], 'selectable' => $can['share']])
                @endif
                @if($retired->isNotEmpty())
                    <details class="disclosure disclosure-inset">
                        <summary>Documentos dados de baja ({{ $retired->count() }})</summary>
                        <div class="table-scroll" role="region" aria-label="Documentos dados de baja" tabindex="0">
                            <table class="data-table">
                                <thead><tr><th scope="col">Documento</th><th scope="col">Versiones</th><th scope="col">Baja</th><th scope="col">Motivo</th></tr></thead>
                                <tbody>
                                    @foreach($retired as $doc)
                                        <tr>
                                            <td><strong>{{ $doc->name }}</strong><small>{{ $doc->type }}</small></td>
                                            <td>{{ $doc->latestVersion?->version ?? 0 }}</td>
                                            <td class="cell-nowrap"><strong>{{ $doc->retiredBy?->name }}</strong><small>{{ $doc->deleted_at->format('d/m/Y H:i') }}</small></td>
                                            <td>{{ $doc->deletion_reason }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                        <x-ui.pie-tabla :total="$retired->count()"/>
                    </details>
                @endif
            </section>

            {{-- Permisos de lectura (solo ADMIN) --}}
            @if($can['share'])
                <section class="form-section" aria-labelledby="seccion-accesos">
                    <div class="form-section-heading">
                        <span class="section-number" aria-hidden="true">{{ $numeros['seccion-accesos'] }}</span>
                        <div><h2 id="seccion-accesos">Permisos de lectura</h2><p>El administrador concede o revoca el acceso para consultar y descargar documentos.</p></div>
                        <div class="section-actions">
                            <x-ui.boton :href="route('permissions.create', $expediente)"><x-ui.icono nombre="agregar" class="icono-sm"/>Asignar permisos</x-ui.boton>
                        </div>
                    </div>
                    @include('permissions.overview')
                </section>
            @endif

        </div>
    </div>

    {{-- Diálogos de formulario (manual §7.9.1). Cada formulario envía _dialogo para reabrirse si vuelve con errores. --}}
    @if($can['edit'])
        <x-ui.dialogo id="editar-datos" titulo="Editar datos" descripcion="Actualiza el título y la descripción. El folio no se modifica.">
            <form method="POST" action="{{ route('expedientes.update', $expediente) }}" data-cargando="Guardando cambios…">
                @csrf @method('PUT')
                <input type="hidden" name="_dialogo" value="editar-datos">
                <div class="form-field">
                    <label for="editar-name">Título<span class="required" aria-hidden="true">*</span></label>
                    <input class="input-text" id="editar-name" name="name" value="{{ $valor('editar-datos', 'name', $expediente->name) }}" required aria-required="true" maxlength="255" @if($conError('editar-datos', 'name')) aria-invalid="true" aria-describedby="editar-name-error" @endif>
                    <x-ui.error-campo campo="name" id="editar-name-error" dialogo="editar-datos"/>
                </div>
                <div class="form-field">
                    <label for="editar-description">Descripción</label>
                    <textarea class="input-text" id="editar-description" name="description" maxlength="10000" @if($conError('editar-datos', 'description')) aria-invalid="true" aria-describedby="editar-description-error" @endif>{{ $valor('editar-datos', 'description', $expediente->description) }}</textarea>
                    <x-ui.error-campo campo="description" id="editar-description-error" dialogo="editar-datos"/>
                </div>
                <div class="modal-actions">
                    <button type="button" class="btn-secondary" data-dialogo-cerrar>Cancelar</button>
                    <x-ui.boton type="submit"><x-ui.icono nombre="check" class="icono-sm"/>Guardar cambios</x-ui.boton>
                </div>
            </form>
        </x-ui.dialogo>

        <x-ui.dialogo id="cargar-documentos" titulo="Cargar documentos" descripcion="Si alguno de los archivos no es válido, no se guarda ninguno.">
            <form method="POST" enctype="multipart/form-data" action="{{ route('documents.store', $expediente) }}" data-cargando="Cargando documentos…">
                @csrf
                <input type="hidden" name="_dialogo" value="cargar-documentos">
                <div class="form-field">
                    <label for="documento-files">Archivos<span class="required" aria-hidden="true">*</span></label>
                    <input class="input-text" id="documento-files" name="files[]" type="file" multiple accept=".pdf,.docx,.xlsx,.jpg,.jpeg,.png" required aria-required="true" aria-describedby="documento-files-hint @if($conError('cargar-documentos', 'files')) documento-files-error @endif" @if($conError('cargar-documentos', 'files')) aria-invalid="true" @endif>
                    <small id="documento-files-hint" class="form-hint">Uno o varios archivos PDF, DOCX, XLSX, JPG o PNG; máximo 10 MB cada uno.</small>
                    <x-ui.error-campo campo="files" id="documento-files-error" dialogo="cargar-documentos"/>
                </div>
                <div class="form-grid two">
                    <div class="form-field">
                        <label for="documento-type">Tipo de documento<span class="required" aria-hidden="true">*</span></label>
                        <select class="input-text" id="documento-type" name="type" required aria-required="true">
                            @foreach(config('expedientes.document_types') as $type)<option @selected($valor('cargar-documentos', 'type', null) === $type)>{{ $type }}</option>@endforeach
                        </select>
                    </div>
                    <div class="form-field">
                        <label for="documento-title">Título</label>
                        <input class="input-text" id="documento-title" name="title" value="{{ $valor('cargar-documentos', 'title', '') }}" maxlength="255" aria-describedby="documento-title-hint">
                    <small id="documento-title-hint" class="form-hint">Solo al cargar un archivo. Se usa como nombre al guardarlo y descargarlo, conservando su extensión. Si se omite, se conserva el nombre original.</small>
                    </div>
                </div>
                <div class="form-field">
                    <label for="documento-description">Descripción</label>
                    <input class="input-text" id="documento-description" name="description" value="{{ $valor('cargar-documentos', 'description', '') }}" maxlength="2000">
                </div>
                <div class="modal-actions">
                    <button type="button" class="btn-secondary" data-dialogo-cerrar>Cancelar</button>
                    <x-ui.boton type="submit"><x-ui.icono nombre="subir" class="icono-sm"/>Cargar documentos</x-ui.boton>
                </div>
            </form>
        </x-ui.dialogo>
    @endif

</x-layouts.institucional>
