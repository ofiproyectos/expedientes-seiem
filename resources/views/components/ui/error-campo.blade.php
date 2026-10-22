@props(['campo', 'id', 'dialogo' => null])
{{-- Con «dialogo», el error solo se muestra en el diálogo cuyo formulario se envió (campo oculto _dialogo). --}}
@php($mensaje = $errors->first($campo) ?: $errors->first($campo.'.*') ?: ($campo === 'file' ? $errors->first('files') : null))
@if((! $dialogo || old('_dialogo') === $dialogo) && $mensaje)
    <small id="{{ $id }}" class="field-error"><x-ui.icono nombre="alerta"/><span>{{ $mensaje }}</span></small>
@endif
