@props(['status'])
@php
    $estilos = [
        'pending' => 'border-dourado/30 bg-dourado/10 text-dourado-texto',
        'confirmed' => 'border-verde/25 bg-verde/10 text-verde',
        'cancelled' => 'border-red-200 bg-red-50 text-red-700',
        'completed' => 'border-texto/15 bg-texto/5 text-texto',
        'no_show' => 'border-orange-200 bg-orange-50 text-orange-700',
    ];
@endphp
<span
    {{ $attributes->merge(['class' => 'inline-flex items-center rounded-full border px-2.5 py-1 text-xs font-semibold ' . ($estilos[$status] ?? 'border-bege bg-bege/30 text-texto')]) }}
>
    {{ \App\Models\Appointment::STATUS_LABELS[$status] ?? $status }}
</span>
