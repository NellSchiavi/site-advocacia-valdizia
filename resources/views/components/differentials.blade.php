@php
    $items = [
        ['icon' => 'shield-check', 'label' => 'Atendimento humanizado'],
        ['icon' => 'document-text', 'label' => 'Orientação clara e transparente'],
        ['icon' => 'handshake', 'label' => 'Compromisso com o seu direito'],
        ['icon' => 'chart-bar', 'label' => 'Experiência e dedicação'],
    ];
@endphp

{{-- Pensado para ser usado sobre fundo escuro (dentro do Hero), como na referência. --}}
<ul class="grid grid-cols-2 gap-x-4 gap-y-5 sm:grid-cols-4 sm:gap-x-0 sm:divide-x sm:divide-white/15">
    @foreach ($items as $item)
        <li class="flex items-start gap-2.5 sm:pr-4 sm:pl-4 sm:first:pl-0">
            <x-ui.icon :name="$item['icon']" class="mt-0.5 h-5 w-5 shrink-0 text-dourado" />
            <span class="text-xs leading-snug font-medium text-white/85">{{ $item['label'] }}</span>
        </li>
    @endforeach
</ul>
