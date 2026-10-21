@props(['campo', 'id'])
@error($campo)
    <small id="{{ $id }}" class="field-error"><x-ui.icono nombre="alerta"/><span>{{ $message }}</span></small>
@enderror
