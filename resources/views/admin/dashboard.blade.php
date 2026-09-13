@php
    $cartoes = [
        [
            'label' => 'Consultas hoje',
            'valor' => $consultasHoje,
            'vazio' => 'Nenhuma consulta hoje.',
            'icone' => 'calendar',
        ],
        [
            'label' => 'Confirmadas (hoje e futuras)',
            'valor' => $consultasConfirmadas,
            'vazio' => 'Nenhuma consulta confirmada.',
            'icone' => 'shield-check',
        ],
        [
            'label' => 'Consultas futuras',
            'valor' => $consultasFuturas,
            'vazio' => 'Nenhuma consulta futura agendada.',
            'icone' => 'clock',
        ],
        [
            'label' => 'Áreas de atuação ativas',
            'valor' => $areasAtivas,
            'vazio' => 'Nenhuma área de atuação ativa.',
            'icone' => 'scale',
        ],
    ];
@endphp

<x-admin-layout title="Dashboard">
    <h1 class="font-serif text-2xl font-semibold text-texto">Dashboard</h1>
    <p class="mt-1 text-sm text-texto/60">Visão geral do agendamento — dados em tempo real.</p>

    <div class="mt-8 grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
        @foreach ($cartoes as $cartao)
            <div class="rounded-2xl border border-bege bg-white p-5">
                <div class="flex items-center gap-2 text-texto/60">
                    <x-ui.icon :name="$cartao['icone']" class="h-4 w-4" />
                    <span class="text-xs font-semibold tracking-wide uppercase">{{ $cartao['label'] }}</span>
                </div>

                @if ($cartao['valor'] > 0)
                    <p class="mt-3 font-serif text-3xl font-semibold text-texto">{{ $cartao['valor'] }}</p>
                @else
                    <p class="mt-3 font-serif text-3xl font-semibold text-texto/30">0</p>
                    <p class="mt-1 text-xs text-texto/50">{{ $cartao['vazio'] }}</p>
                @endif
            </div>
        @endforeach
    </div>

    <div class="mt-8 rounded-2xl border border-dourado/30 bg-white p-5">
        <p class="text-sm text-texto/70">
            A agenda administrativa, disponibilidade, feriados e configurações chegam nas próximas fases do painel.
            Por enquanto, este dashboard reflete apenas os dados já existentes no banco.
        </p>
    </div>
</x-admin-layout>
