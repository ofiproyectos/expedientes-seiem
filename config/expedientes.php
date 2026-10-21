<?php

return [
    'platform_name' => 'Sistema de Expedientes',
    'roles' => ['ADMIN' => 'Administrador', 'LOADER' => 'Cargador', 'READER' => 'Lector'],
    // Registros por página permitidos en los listados (RF-BUS-003); el primero es el predeterminado.
    'per_page' => [25, 10, 50, 100],
    'document_types' => ['Acta de nacimiento', 'INE', 'CURP', 'Comprobante de domicilio', 'Identificación', 'Oficio', 'Solicitud', 'Contrato', 'Otro'],
    'max_file_kb' => 10240,
];
