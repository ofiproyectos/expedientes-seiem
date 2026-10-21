@props(['nombre' => 'documento'])
@php
    $rutas = [
        'documento' => 'M8 3H5a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V8M14 3v6h6M8 13h8M8 17h5M14 3l7 6',
        'agregar' => 'M12 5v14M5 12h14',
        'inicio' => 'M3 10l9-7 9 7M5 9v12h14V9M9 21v-7h6v7',
        'usuarios' => 'M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2M9 11a4 4 0 1 0 0-8 4 4 0 0 0 0 8M16 3a4 4 0 0 1 0 8M22 21v-2a4 4 0 0 0-3-3.87',
        'buscar' => 'M21 21l-5-5M17 10a7 7 0 1 1-14 0 7 7 0 0 1 14 0',
        'salir' => 'M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4M16 17l5-5-5-5M21 12H9',
        'check' => 'M20 6L9 17l-5-5',
        'desbloquear' => 'M5 11h14v10H5zM8 11V7a4 4 0 0 1 7.75-1.4',
        'candado' =>'M5 11h14v10H5zM8 11V7a4 4 0 0 1 8 0v4',
        'flecha' => 'M5 12h14M13 6l6 6-6 6',
        'actividad' => 'M3 12h4l3-8 4 16 3-8h4',
        'importar' => 'M12 3v12M7 10l5 5 5-5M4 17v4h16v-4',
        'descargar' => 'M12 3v12M7 10l5 5 5-5M4 21h16',
        'subir' => 'M12 15V3M7 8l5-5 5 5M4 17v4h16v-4',
        'cerrar' => 'M18 6L6 18M6 6l12 12',
        'menu' => 'M4 6h16M4 12h16M4 18h16',
        'alerta' => 'M12 3l10 18H2zM12 10v5M12 18h.01',
        'info' => 'M12 21a9 9 0 1 0 0-18 9 9 0 0 0 0 18zM12 11v6M12 7.5h.01',
        'editar' => 'M4 20h4L19 9l-4-4L4 16zM14 6l4 4',
        'eliminar' => 'M4 7h16M10 11v6M14 11v6M6 7l1 13h10l1-13M9 7V4h6v3',
        'calendario' => 'M4 5h16v16H4zM4 10h16M8 3v4M16 3v4',
        'camara' => 'M4 8h3l2-3h6l2 3h3v11H4zM12 17a4 4 0 1 0 0-8 4 4 0 0 0 0 8',
        'actualizar' => 'M21 12a9 9 0 1 1-3-6.7M21 4v5h-5',
        'devolver' =>'M9 14L4 9l5-5M4 9h11a5 5 0 0 1 0 10h-3',
    ];
@endphp
<svg {{ $attributes->class(['icono', 'icon-'.$nombre]) }} viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><path d="{{ $rutas[$nombre] ?? $rutas['documento'] }}"/></svg>
