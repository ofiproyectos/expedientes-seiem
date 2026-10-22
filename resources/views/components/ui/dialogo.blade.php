@props(['id', 'titulo', 'descripcion' => null, 'amplio' => false, 'peligro' => false])
{{-- Diálogo de formulario (manual §7.9.1). Se reabre al cargar si su formulario volvió con errores de validación. --}}
<dialog id="{{ $id }}" {{ $attributes->class(['modal', 'modal-form', 'modal-wide' => $amplio, 'is-danger' => $peligro]) }} aria-labelledby="{{ $id }}-titulo" @if($descripcion) aria-describedby="{{ $id }}-descripcion" @endif data-dialogo @if($errors->any() && old('_dialogo') === $id) data-abrir-al-cargar @endif>
    <div class="modal-content">
        <div class="modal-header">
            <div>
                <h2 id="{{ $id }}-titulo">{{ $titulo }}</h2>
                @if($descripcion)<p id="{{ $id }}-descripcion">{{ $descripcion }}</p>@endif
            </div>
            <button type="button" class="icon-button" data-dialogo-cerrar aria-label="Cerrar"><x-ui.icono nombre="cerrar"/></button>
        </div>
        @if($errors->any() && old('_dialogo') === $id)
            <div class="alert alert-error" role="alert" tabindex="-1" data-dialog-error-summary>
                <x-ui.icono nombre="alerta"/><div><strong>Revisa la información antes de continuar.</strong><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>
            </div>
        @endif
        @if(str_contains((string) $slot, 'aria-required="true"'))
            <p class="form-hint">Los campos marcados con <span class="required" aria-hidden="true">*</span> son obligatorios.</p>
        @endif
        {{ $slot }}
    </div>
</dialog>
