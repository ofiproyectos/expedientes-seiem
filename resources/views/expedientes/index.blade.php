@php($filtrando = collect(request()->only('q', 'status', 'responsible', 'from', 'to'))->filter(fn ($v) => filled($v))->isNotEmpty())
<x-layouts.institucional titulo="Expedientes">
    <div class="page-title">
        <div>
            <h1>Expedientes</h1>
            <p class="muted">{{ auth()->user()->isAdmin() ? 'Consulta todos los expedientes y administra sus permisos de lectura.' : (auth()->user()->isLoader() ? 'Crea y administra los expedientes bajo tu responsabilidad.' : 'Consulta los expedientes y documentos compartidos contigo.') }}</p>
        </div>
    </div>

    @can('create', App\Models\Expediente::class)
        <details class="card disclosure" @if($errors->hasAny(['name', 'type', 'description'])) open @endif>
            <summary><x-ui.icono nombre="agregar"/>Crear expediente</summary>
            <p class="form-hint">El folio se genera automáticamente y el expediente se crea en estado <strong>Activo</strong> con usted como responsable. Los campos marcados con <span class="required" aria-hidden="true">*</span> son obligatorios.</p>
            <form method="POST" action="{{ route('expedientes.store') }}" data-cargando="Creando expediente…">
                @csrf
                <div class="form-grid two">
                    <div class="form-field">
                        <label for="nuevo-name">Título<span class="required" aria-hidden="true">*</span></label>
                        <input class="input-text" id="nuevo-name" name="name" value="{{ old('name') }}" required aria-required="true" maxlength="255" @error('name') aria-invalid="true" aria-describedby="nuevo-name-error" @enderror>
                        <x-ui.error-campo campo="name" id="nuevo-name-error"/>
                    </div>
                    <div class="form-field">
                        <label for="nuevo-type">Tipo de expediente<span class="required" aria-hidden="true">*</span></label>
                        <input class="input-text" id="nuevo-type" name="type" value="{{ old('type') }}" list="tipos-expediente" required aria-required="true" maxlength="100" aria-describedby="nuevo-type-hint @error('type') nuevo-type-error @enderror" @error('type') aria-invalid="true" @enderror>
                        <datalist id="tipos-expediente">@foreach($types as $type)<option value="{{ $type }}">@endforeach</datalist>
                        <small id="nuevo-type-hint" class="form-hint">Por ejemplo: Personal o Administrativo.</small>
                        <x-ui.error-campo campo="type" id="nuevo-type-error"/>
                    </div>
                </div>
                <div class="form-field">
                    <label for="nuevo-description">Descripción</label>
                    <textarea class="input-text" id="nuevo-description" name="description" maxlength="10000" @error('description') aria-invalid="true" aria-describedby="nuevo-description-error" @enderror>{{ old('description') }}</textarea>
                    <x-ui.error-campo campo="description" id="nuevo-description-error"/>
                </div>
                <div class="form-actions">
                    <x-ui.boton type="submit"><x-ui.icono nombre="agregar" class="icono-sm"/>Crear expediente</x-ui.boton>
                </div>
            </form>
        </details>
    @endcan

    <section class="card" aria-labelledby="titulo-listado" data-listado-dinamico>
        <div class="requests-header">
            <div>
                <div class="section-title"><x-ui.icono nombre="documento"/><h2 id="titulo-listado">Listado</h2></div>
                <span class="muted" data-listado-total>{{ $expedientes->total() }} {{ $expedientes->total() === 1 ? 'expediente' : 'expedientes' }}</span>
            </div>
        </div>

        <form class="filter-bar" method="GET" action="{{ route('expedientes.index') }}" role="search">
            <div class="form-field search-field">
                <label for="filtro-q">Folio, título, tipo o responsable</label>
                <div class="input-icon"><x-ui.icono nombre="buscar"/><input class="input-text" id="filtro-q" name="q" value="{{ request('q') }}" maxlength="150" placeholder="Buscar expediente"></div>
            </div>
            <div class="form-field">
                <label for="filtro-status">Estado</label>
                <select class="input-text" id="filtro-status" name="status">
                    <option value="">Todos</option>
                    @foreach(App\Enums\ExpedienteStatus::cases() as $estado)<option value="{{ $estado->value }}" @selected(request('status') === $estado->value)>{{ $estado->label() }}</option>@endforeach
                </select>
            </div>
            @if($responsibles->count() > 1)
                <div class="form-field">
                    <label for="filtro-responsible">Responsable</label>
                    <select class="input-text" id="filtro-responsible" name="responsible">
                        <option value="">Todos</option>
                        @foreach($responsibles as $persona)<option value="{{ $persona->id }}" @selected((string) request('responsible') === (string) $persona->id)>{{ $persona->name }}</option>@endforeach
                    </select>
                </div>
            @endif
            <div class="form-field">
                <label for="filtro-from">Creado desde</label>
                <input class="input-text" id="filtro-from" type="date" name="from" value="{{ request('from') }}">
            </div>
            <div class="form-field">
                <label for="filtro-to">Creado hasta</label>
                <input class="input-text" id="filtro-to" type="date" name="to" value="{{ request('to') }}">
            </div>
            <div class="form-field">
                <label for="filtro-sort">Ordenar por</label>
                <select class="input-text" id="filtro-sort" name="sort">
                    @foreach(App\Http\Controllers\ExpedienteController::SORTS as $clave => [, , $etiqueta])<option value="{{ $clave }}" @selected(request('sort', 'recientes') === $clave)>{{ $etiqueta }}</option>@endforeach
                </select>
            </div>
            <div class="form-actions">
                <x-ui.boton variante="secondary" :href="route('expedientes.index')" data-limpiar-filtros title="Limpiar filtros" aria-label="Limpiar filtros"><x-ui.icono nombre="actualizar" class="icono-sm"/></x-ui.boton>
                <noscript><x-ui.boton type="submit">Aplicar filtros</x-ui.boton></noscript>
            </div>
        </form>

        <p class="muted" role="status" data-listado-aviso hidden></p>
        <div data-listado-resultados>
        <div class="table-scroll" role="region" aria-labelledby="titulo-listado" tabindex="0">
            <table class="data-table requests-table">
                <thead>
                    <tr>
                        <th scope="col">Expediente</th>
                        <th scope="col">Tipo</th>
                        <th scope="col">Responsable</th>
                        <th scope="col">Documentos</th>
                        <th scope="col">Creación</th>
                        <th scope="col">Estado</th>
                        <th scope="col" class="cell-action">Acción</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($expedientes as $item)
                        <tr>
                            <td class="cell-primary"><strong>{{ $item->name }}</strong><small>{{ $item->folio }}</small></td>
                            <td>{{ $item->type }}</td>
                            <td>{{ $item->responsible->name }}</td>
                            <td>{{ $item->documents_count }}</td>
                            <td class="cell-nowrap">{{ $item->created_at->format('d/m/Y') }}<small>{{ $item->created_at->format('H:i') }}</small></td>
                            <td><span class="status-badge {{ $item->status->badge() }}">{{ $item->status->label() }}</span></td>
                            <td class="cell-action">
                                @can('update', $item)
                                    <a class="table-action" href="{{ route('expedientes.show', $item) }}"><x-ui.icono nombre="editar"/>Editar<span class="sr-only"> expediente {{ $item->name }}</span></a>
                                @else
                                    <a class="table-action" href="{{ route('expedientes.show', $item) }}"><x-ui.icono nombre="buscar"/>Consultar<span class="sr-only"> expediente {{ $item->name }}</span></a>
                                @endcan
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="empty-state-cell">
                                <div class="empty-state">
                                    <x-ui.icono nombre="{{ $filtrando ? 'buscar' : 'documento' }}"/>
                                    @if($filtrando)
                                        <h3>Sin coincidencias</h3>
                                        <p>Ningún expediente coincide con los filtros. Ajusta la búsqueda o quita algún filtro.</p>
                                    @else
                                        <h3>Aún no hay expedientes</h3>
                                        <p>Cuando se cree o se comparta un expediente contigo, aparecerá en este listado.</p>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        {{ $expedientes->links('pagination.institucional') }}
        </div>
    </section>

    @if($sharedDocuments->isNotEmpty())
        <section class="card" aria-labelledby="titulo-compartidos">
            <div class="section-heading">
                <div>
                    <h2 id="titulo-compartidos">Documentos compartidos contigo</h2>
                    <p class="muted">Tienes acceso a estos documentos de forma individual, sin acceso al resto de su expediente.</p>
                </div>
            </div>
            @foreach($sharedDocuments as $document)
                @include('expedientes.document', ['editable' => false, 'history' => false])
            @endforeach
        </section>
    @endif
</x-layouts.institucional>
