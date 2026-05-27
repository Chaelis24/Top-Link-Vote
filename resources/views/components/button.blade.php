@props([
    'type' => 'button',
    'variant' => 'blue',
    'width' => '160px',
    'height' => '40px',
    'fontSize' => '14px',
])

@php
    $baseStyles = 'btn fw-bold shadow-sm d-inline-flex align-items-center justify-content-center rounded-pill';

    $variants = [
        'blue' => 'btn-primary text-white',
        'gray' => 'btn-secondary text-white',
        'glow' => 'btn-glow text-white',
        'link' => 'btn-link text-muted text-decoration-none shadow-none',
    ];

    $classes = $variants[$variant] ?? $variants['blue'];
@endphp

<button {{ $attributes->merge([
    'type' => $type,
    'class' => "$baseStyles $classes",
]) }}
    style="{{ $attributes->has('class') && str_contains($attributes->get('class'), 'w-') ? '' : 'width: ' . $width . ';' }}
           {{ $attributes->has('class') && str_contains($attributes->get('class'), 'h-') ? '' : 'height: ' . $height . ';' }}">
    <span wire:loading class="spinner-border spinner-border-sm me-2" role="status" aria-hidden="true"></span>

    <span>{{ $slot }}</span>
</button>
