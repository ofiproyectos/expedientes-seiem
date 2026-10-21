<div class="toast-region" aria-live="polite"></div>
@if(session('status'))
    <template data-aviso-inicial data-tipo="success" data-titulo="Operación realizada" data-mensaje="{{ session('status') }}"></template>
@endif
