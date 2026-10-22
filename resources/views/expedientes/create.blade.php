<x-layouts.institucional titulo="Crear expediente">
    <x-ui.migas :enlaces="[route('expedientes.index') => 'Expedientes']" actual="Crear expediente"/>
    <div class="page-title">
        <div>
            <h1>Crear expediente</h1>
            <p class="muted">Registra los datos del expediente. Después podrás cargar sus documentos.</p>
        </div>
    </div>
    <section class="card narrow-card" aria-labelledby="titulo-datos">
        <div class="section-title"><x-ui.icono nombre="documento"/><h2 id="titulo-datos">Datos del expediente</h2></div>
        <p class="form-hint">El folio (por ejemplo, EXP-{{ now()->format('Y') }}-000001) se asigna automáticamente al crearlo, con tu cuenta como responsable. Los campos marcados con <span class="required" aria-hidden="true">*</span> son obligatorios.</p>
        <form method="POST" action="{{ route('expedientes.store') }}" data-cargando="Creando expediente…">
            @csrf
            <div class="form-field">
                <label for="nuevo-name">Título<span class="required" aria-hidden="true">*</span></label>
                <input class="input-text" id="nuevo-name" name="name" value="{{ old('name') }}" required aria-required="true" maxlength="255" @error('name') aria-invalid="true" aria-describedby="nuevo-name-error" @enderror>
                <x-ui.error-campo campo="name" id="nuevo-name-error"/>
            </div>
            <div class="form-field">
                <label for="nuevo-description">Descripción</label>
                <textarea class="input-text" id="nuevo-description" name="description" maxlength="10000" @error('description') aria-invalid="true" aria-describedby="nuevo-description-error" @enderror>{{ old('description') }}</textarea>
                <x-ui.error-campo campo="description" id="nuevo-description-error"/>
            </div>
            <div class="form-actions">
                <x-ui.boton variante="secondary" :href="route('expedientes.index')">Cancelar</x-ui.boton>
                <x-ui.boton type="submit"><x-ui.icono nombre="agregar" class="icono-sm"/>Crear expediente</x-ui.boton>
            </div>
        </form>
    </section>
</x-layouts.institucional>
