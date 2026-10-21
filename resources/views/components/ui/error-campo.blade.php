@props(['campo', 'id', 'dialogo' => null])
{{-- Con «dialogo», el error solo se muestra en el diálogo cuyo formulario se envió (campo oculto _dialogo). --}}
@if(! $dialogo || old('_dialogo') === $dialogo)
    @error($campo)
        <small id="{{ $id }}" class="field-error"><x-ui.icono nombre="alerta"/><span>{{ $message }}</span></small>
    @enderror
@endif
