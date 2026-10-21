<div class="table-scroll" role="region" aria-label="Eventos de auditoría" tabindex="0">
    <table class="data-table">
        <thead>
            <tr>
                <th scope="col">Fecha</th>
                <th scope="col">Usuario</th>
                <th scope="col">Acción</th>
                <th scope="col">Resultado</th>
                @if($mostrarRecurso)<th scope="col">Recurso</th>@endif
                <th scope="col">Detalle</th>
            </tr>
        </thead>
        <tbody>
            @forelse($events as $event)
                <tr>
                    <td class="cell-nowrap">{{ $event->created_at->format('d/m/Y') }}<small>{{ $event->created_at->format('H:i:s') }}</small></td>
                    <td>@if($event->user || $event->username)<strong>{{ $event->user?->name ?? $event->username }}</strong><small>{{ $event->user?->username ?? $event->username }}</small>@else<span class="muted">No identificado</span>@endif</td>
                    <td>{{ $event->actionLabel() }}</td>
                    <td><span class="status-badge {{ $event->resultBadge() }}">{{ App\Models\AuditEvent::RESULTS[$event->result] ?? $event->result }}</span></td>
                    @if($mostrarRecurso)
                        <td class="cell-nowrap">@if($event->resource_type){{ App\Models\AuditEvent::RESOURCES[$event->resource_type] ?? $event->resource_type }}@if($event->resource_id) #{{ $event->resource_id }}@endif @else — @endif
                            @if($event->expediente_id)<small><a href="{{ route('expedientes.show', $event->expediente_id) }}">Expediente #{{ $event->expediente_id }}</a></small>@endif</td>
                    @endif
                    <td>{{ $event->description }}@if(! empty($event->metadata['reason']))<small>Motivo: {{ $event->metadata['reason'] }}</small>@endif<small>IP {{ $event->ip_address ?? '—' }}</small></td>
                </tr>
            @empty
                <tr>
                    <td colspan="{{ $mostrarRecurso ? 6 : 5 }}" class="empty-state-cell">
                        <div class="empty-state"><x-ui.icono nombre="actividad"/><h3>Sin eventos</h3><p>No hay eventos registrados con estos criterios.</p></div>
                    </td>
                </tr>
            @endforelse
        </tbody>
    </table>
</div>
{{-- Con paginador, el pie lo dibuja pagination/institucional. --}}
@unless($events instanceof Illuminate\Contracts\Pagination\Paginator)
    <x-ui.pie-tabla :total="$events->count()"/>
@endunless
