{{-- RF-PRM-003: permisos vigentes y revocados, con quién los concedió y quién los revocó. --}}
<div class="table-scroll" role="region" aria-label="Permisos de lectura" tabindex="0">
    <table class="data-table">
        <thead>
            <tr>
                <th scope="col">{{ $mostrarExpediente ? 'Expediente' : 'Lector' }}</th>
                <th scope="col">Alcance</th>
                <th scope="col">Vigencia</th>
                <th scope="col">Descarga</th>
                <th scope="col">Otorgado por</th>
                <th scope="col">Estado</th>
                @if($revocable)<th scope="col" class="cell-action">Acción</th>@endif
            </tr>
        </thead>
        <tbody>
            @forelse($grants as $grant)
                <tr>
                    @if($mostrarExpediente)
                        <td class="cell-primary"><a href="{{ route('expedientes.show', $grant->expediente) }}"><strong>{{ $grant->expediente->name }}</strong></a><small>{{ $grant->expediente->folio }}</small></td>
                    @else
                        <td><strong>{{ $grant->user->name }}</strong><small>{{ $grant->user->username }}</small></td>
                    @endif
                    <td>{{ $grant->document?->name ?? 'Expediente completo' }}@if($grant->document?->trashed())<small>Documento dado de baja</small>@endif</td>
                    <td class="cell-nowrap">Desde {{ $grant->starts_at->format('d/m/Y H:i') }}<small>{{ $grant->expires_at ? 'Hasta '.$grant->expires_at->format('d/m/Y H:i') : 'Sin expiración' }}</small></td>
                    <td>{{ $grant->can_download ? 'Sí' : 'No' }}</td>
                    <td>{{ $grant->grantor?->name }}<small>{{ $grant->created_at->format('d/m/Y H:i') }}</small></td>
                    <td><x-ui.estado-permiso :grant="$grant"/>@if($grant->revoked_at)<small>{{ $grant->revoker?->name ? 'Por '.$grant->revoker->name.' · ' : '' }}{{ $grant->revoked_at->format('d/m/Y H:i') }}</small>@endif</td>
                    @if($revocable)
                        <td class="cell-action">
                            @unless($grant->revoked_at)
                                <form method="POST" action="{{ route('grants.revoke', $grant) }}" data-cargando="Revocando permiso…">
                                    @csrf @method('DELETE')
                                    <button type="submit" class="table-action text-button" data-confirm="{{ $grant->user->name }} dejará de tener acceso a {{ $grant->document?->name ?? 'este expediente' }} desde su siguiente solicitud. El registro del permiso se conserva." data-confirm-title="Revocar permiso" data-confirm-accept="Sí, revocar"><x-ui.icono nombre="candado"/>Revocar<span class="sr-only"> permiso de {{ $grant->user->name }}</span></button>
                                </form>
                            @endunless
                        </td>
                    @endif
                </tr>
            @empty
                <tr>
                    <td colspan="{{ $revocable ? 7 : 6 }}" class="empty-state-cell">
                        <div class="empty-state"><x-ui.icono nombre="candado"/><h3>Sin permisos asignados</h3><p>{{ $mostrarExpediente ? 'Los permisos de lectura se asignan desde cada expediente.' : 'Usa «Asignar permiso» para conceder acceso a un lector.' }}</p></div>
                    </td>
                </tr>
            @endforelse
        </tbody>
    </table>
</div>
<x-ui.pie-tabla :total="$grants->count()"/>
