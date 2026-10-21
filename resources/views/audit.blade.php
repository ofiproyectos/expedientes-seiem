<x-layouts.institucional titulo="Auditoría">
    <div class="page-title">
        <div>
            <h1>Auditoría</h1>
            <p class="muted">Historial de operaciones de autenticación, usuarios, expedientes, documentos y permisos. Los registros no se pueden modificar ni eliminar.</p>
        </div>
    </div>

    <section class="card" aria-labelledby="titulo-auditoria">
        <div class="requests-header">
            <div>
                <div class="section-title"><x-ui.icono nombre="actividad"/><h2 id="titulo-auditoria">Eventos</h2></div>
                <span class="muted">{{ $events->total() }} {{ $events->total() === 1 ? 'evento' : 'eventos' }}@if(request('expediente_id')) del expediente #{{ request('expediente_id') }}@endif</span>
            </div>
        </div>

        <form class="filter-bar" method="GET" action="{{ route('audit.index') }}">
            @if(request('expediente_id'))<input type="hidden" name="expediente_id" value="{{ request('expediente_id') }}">@endif
            <div class="form-field">
                <label for="audit-user">Usuario</label>
                <select class="input-text" id="audit-user" name="user_id">
                    <option value="">Todos</option>
                    @foreach($users as $persona)<option value="{{ $persona->id }}" @selected((string) request('user_id') === (string) $persona->id)>{{ $persona->name }} · {{ $persona->username }}</option>@endforeach
                </select>
            </div>
            <div class="form-field">
                <label for="audit-action">Acción</label>
                <select class="input-text" id="audit-action" name="action">
                    <option value="">Todas</option>
                    @foreach(App\Models\AuditEvent::ACTIONS as $clave => $etiqueta)<option value="{{ $clave }}" @selected(request('action') === $clave)>{{ $etiqueta }}</option>@endforeach
                </select>
            </div>
            <div class="form-field">
                <label for="audit-result">Resultado</label>
                <select class="input-text" id="audit-result" name="result">
                    <option value="">Todos</option>
                    @foreach(App\Models\AuditEvent::RESULTS as $clave => $etiqueta)<option value="{{ $clave }}" @selected(request('result') === $clave)>{{ $etiqueta }}</option>@endforeach
                </select>
            </div>
            <div class="form-field">
                <label for="audit-resource">Recurso</label>
                <select class="input-text" id="audit-resource" name="resource_type">
                    <option value="">Todos</option>
                    @foreach(App\Models\AuditEvent::RESOURCES as $clave => $etiqueta)<option value="{{ $clave }}" @selected(request('resource_type') === $clave)>{{ $etiqueta }}</option>@endforeach
                </select>
            </div>
            <div class="form-field">
                <label for="audit-resource-id">Identificador del recurso</label>
                <input class="input-text" id="audit-resource-id" type="number" min="1" name="resource_id" value="{{ request('resource_id') }}">
            </div>
            <div class="form-field">
                <label for="audit-from">Desde</label>
                <input class="input-text" id="audit-from" type="date" name="from" value="{{ request('from') }}">
            </div>
            <div class="form-field">
                <label for="audit-to">Hasta</label>
                <input class="input-text" id="audit-to" type="date" name="to" value="{{ request('to') }}">
            </div>
            <x-per-page/>
            <div class="form-actions">
                <x-ui.boton type="submit"><x-ui.icono nombre="buscar" class="icono-sm"/>Aplicar filtros</x-ui.boton>
                <x-ui.boton variante="secondary" :href="route('audit.index')" title="Limpiar filtros" aria-label="Limpiar filtros"><x-ui.icono nombre="actualizar" class="icono-sm"/></x-ui.boton>
            </div>
        </form>

        @include('partials.audit-table', ['events' => $events, 'mostrarRecurso' => true])
        {{ $events->links('pagination.institucional') }}
    </section>
</x-layouts.institucional>
