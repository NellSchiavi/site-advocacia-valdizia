@php
    $whatsapp = 'https://api.whatsapp.com/send/?phone=5511994227774&text=Ol%C3%A1%20Dra.%20Valdizia%2C%20vim%20pelo%20seu%20site%20e%20gostaria%20de%20tirar%20algumas%20d%C3%BAvidas.&type=phone_number&app_absent=0';
@endphp

<x-layout title="Consulta agendada — Dra. Valdizia Melo">
    <section class="bg-offwhite px-6 py-20 lg:px-10 lg:py-28">
        <div class="mx-auto max-w-xl text-center">
            <span class="mx-auto flex h-16 w-16 items-center justify-center rounded-full bg-verde text-white">
                <x-ui.icon name="shield-check" class="h-8 w-8" />
            </span>

            <h1 class="mt-6 font-serif text-3xl font-semibold text-texto sm:text-4xl">
                Consulta agendada com sucesso.
            </h1>
            <p class="mt-3 text-base text-texto/70">
                Obrigada pelo contato, {{ $appointment->nome_cliente }}. Aqui está o resumo do seu agendamento:
            </p>

            <dl class="mt-8 grid grid-cols-1 gap-4 rounded-2xl border border-bege bg-white p-6 text-left sm:grid-cols-3 sm:p-8">
                <div>
                    <dt class="text-xs font-semibold tracking-wide text-texto/50 uppercase">Área</dt>
                    <dd class="mt-1 font-serif text-base text-texto">{{ $appointment->areaAtuacao->nome_publico }}</dd>
                </div>
                <div>
                    <dt class="text-xs font-semibold tracking-wide text-texto/50 uppercase">Data</dt>
                    <dd class="mt-1 font-serif text-base text-texto capitalize">
                        {{ \Carbon\CarbonImmutable::parse($appointment->data)->translatedFormat('d \d\e F \d\e Y') }}
                    </dd>
                </div>
                <div>
                    <dt class="text-xs font-semibold tracking-wide text-texto/50 uppercase">Horário</dt>
                    <dd class="mt-1 font-serif text-base text-texto">
                        {{ \Illuminate\Support\Carbon::parse($appointment->hora_inicio)->format('H:i') }}
                    </dd>
                </div>
            </dl>

            <p class="mt-8 text-sm text-texto/60">
                Você receberá a confirmação também por e-mail. Se precisar, também pode
                falar diretamente pelo WhatsApp.
            </p>

            <div class="mt-6 flex flex-col items-center gap-4 sm:flex-row sm:justify-center">
                <a href="{{ $whatsapp }}" target="_blank" rel="noopener" class="btn-verde">
                    <x-ui.icon name="whatsapp" class="h-4 w-4" />
                    Falar pelo WhatsApp
                </a>
                <a href="{{ route('home') }}" class="link-arrow text-dourado-texto">
                    Voltar para a página inicial
                    <x-ui.icon name="arrow-right" class="h-4 w-4" />
                </a>
            </div>
        </div>
    </section>
</x-layout>
