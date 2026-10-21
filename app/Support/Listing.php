<?php

namespace App\Support;

use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/** Registros por página configurables dentro de límites (RF-BUS-003). */
class Listing
{
    public static function options(): array
    {
        $options = config('expedientes.per_page');
        sort($options);

        return $options;
    }

    public static function perPageRule(): array
    {
        return ['nullable', 'integer', Rule::in(config('expedientes.per_page'))];
    }

    public static function perPage(Request $request): int
    {
        $value = (int) $request->input('per_page');

        return in_array($value, config('expedientes.per_page'), true) ? $value : config('expedientes.per_page')[0];
    }
}
