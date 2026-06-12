<div>
    @props([
        'variant' => 'edit',
        'size' => '32px',
        'fontSize' => '1rem',
        'borderRadius' => '6px',
    ])

    @php
        $baseStyles = 'btn border-0 d-inline-flex align-items-center justify-content-center rounded-circle p-0';

        $variants = [
            'edit' => 'btn-icon btn-edit',
            'approve' => 'btn-icon btn-approve',
            'delete' => 'btn-icon btn-delete',
            'custom' => '',
        ];

        $classes = $variants[$variant] ?? $variants['edit'];
        $hasWireClick = $attributes->has('wire:click');
        $wireClickAction = $attributes->get('wire:click');
    @endphp

    <button {{ $attributes->merge([
        'type' => 'button',
        'class' => "$baseStyles $classes",
    ]) }}
        style="width: {{ $size }}; height: {{ $size }}; min-width: {{ $size }}; min-height: {{ $size }}; border-radius: {{ $borderRadius }}; font-size: {{ $fontSize }};">

        @if ($variant !== 'custom' && $hasWireClick)
            <span wire:loading.remove wire:target="{{ $wireClickAction }}">
                {{ $slot }}
            </span>
            <span wire:loading wire:target="{{ $wireClickAction }}" class="spinner-border spinner-border-sm"
                style="width: 12px; height: 12px;"></span>
        @else
            <span>
                {{ $slot }}
            </span>
        @endif
    </button>
</div>
