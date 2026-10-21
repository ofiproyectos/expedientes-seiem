<x-layouts.institucional titulo="Asignar permisos">
    <x-ui.migas :enlaces="[route('expedientes.index') => 'Expedientes', route('expedientes.show', $expediente) => $expediente->name]" actual="Asignar permisos"/>
    <div class="page-title"><div><h1>Asignar permisos</h1><p class="muted">{{ $expediente->folio }} · {{ $expediente->name }}</p></div></div>
    <noscript><p class="alert alert-error">Activa JavaScript para seleccionar y revisar los permisos por lote. No se ha asignado ningún permiso.</p></noscript>
    <form method="POST" action="{{ route('permissions.store', $expediente) }}" data-permission-wizard data-preview-url="{{ route('permissions.preview', $expediente) }}" data-cargando="Asignando permisos…">
        @csrf
        <input type="hidden" name="review_token" data-review-token>
        <input type="hidden" name="selection" data-batch-selection>
        <ol class="permission-steps" aria-label="Pasos de asignación"><li data-step-label aria-current="step">1. Documentos</li><li data-step-label>2. Lectores</li><li data-step-label>3. Revisar y asignar</li></ol>
        <p class="alert alert-error" role="alert" data-batch-error hidden></p>
        <section class="card" data-batch-step="0" aria-labelledby="alcance-titulo">
            <div class="form-section-heading"><span class="section-number" aria-hidden="true">01</span><div><h2 id="alcance-titulo" tabindex="-1">Documentos</h2><p>Elige el alcance del acceso.</p></div></div>
            <div class="form-field">
                <label class="checkbox-label"><input type="radio" name="scope" value="documents" @checked(($initial['scope'] ?? 'documents') === 'documents')>Documentos específicos</label>
                <label class="checkbox-label"><input type="radio" name="scope" value="expediente" @checked(($initial['scope'] ?? '') === 'expediente')>Expediente completo</label>
                <p class="form-hint" data-scope-help>Los documentos específicos no incluyen los que se agreguen después. El expediente completo incluye documentos actuales y futuros, siempre en su versión vigente.</p>
            </div>
            <div data-document-picker>
                <x-ui.seleccion-tabla id="batch-documents" titulo="Nombre del documento" columna="Documento" detalle="Tipo" :items="$documents->map(fn ($d) => ['id' => $d->id, 'label' => $d->name, 'detail' => $d->type])" :selected="$initial['document_ids'] ?? []"/>
            </div>
        </section>
        <section class="card" data-batch-step="1" aria-labelledby="lectores-titulo" hidden>
            <div class="form-section-heading"><span class="section-number" aria-hidden="true">02</span><div><h2 id="lectores-titulo" tabindex="-1">Lectores</h2><p>Selecciona las cuentas que podrán consultar los documentos.</p></div></div>
            <p class="form-hint">Solo lectores activos. La selección se conserva al buscar y cambiar de página. Seleccionar todos no incluye usuarios que se creen después.</p>
            <x-ui.seleccion-tabla id="batch-readers" titulo="Nombre o usuario" columna="Lector" detalle="Usuario" :items="$readers->map(fn ($r) => ['id' => $r->id, 'label' => $r->name, 'detail' => $r->username])" :selected="$initial['user_ids'] ?? []"/>
        </section>
        <section class="card" data-batch-step="2" aria-labelledby="revision-titulo" hidden>
            <div class="form-section-heading"><span class="section-number" aria-hidden="true">03</span><div><h2 id="revision-titulo" tabindex="-1">Revisar y asignar</h2><p>Verifica la vigencia del acceso.</p></div></div>
            <p class="form-hint">El acceso permite consultar y descargar. Los permisos completamente cubiertos se consolidan; los que tienen mayor vigencia se conservan.</p>
            <div class="form-grid two">
                <div class="form-field"><label for="batch-starts">Inicio (vacío: inmediato)</label><input id="batch-starts" class="input-text" type="datetime-local" name="starts_at" value="{{ $initial['starts_at'] ?? '' }}"></div>
                <div class="form-field"><label for="batch-expires">Expiración (opcional)</label><input id="batch-expires" class="input-text" type="datetime-local" name="expires_at" value="{{ $initial['expires_at'] ?? '' }}"></div>
            </div>
            <div class="permission-review" data-review-summary aria-live="polite"></div>
        </section>
        <div class="form-actions">
            <x-ui.boton variante="secondary" :href="route('expedientes.show', $expediente).'#seccion-accesos'">Cancelar</x-ui.boton>
            <button type="button" class="btn-secondary" data-batch-back hidden>Anterior</button>
            <button type="button" class="btn-primary" data-batch-next>Siguiente<x-ui.icono nombre="flecha" class="icono-sm"/></button>
            <button type="submit" class="btn-primary" data-batch-save hidden disabled><x-ui.icono nombre="agregar" class="icono-sm"/>Asignar permisos</button>
        </div>
    </form>
</x-layouts.institucional>
