@props(['variante' => 'primary', 'href' => null])
@php($clase = match ($variante) { 'secondary' => 'btn-secondary', 'danger' => 'btn-danger', default => 'btn-primary' })
@if($href)
    <a href="{{ $href }}" {{ $attributes->class([$clase]) }}>{{ $slot }}</a>
@else
    <button {{ $attributes->merge(['type' => 'button'])->class([$clase]) }}>{{ $slot }}</button>
@endif
