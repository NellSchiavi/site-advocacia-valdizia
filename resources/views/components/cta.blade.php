@php
    $whatsapp = 'https://api.whatsapp.com/send/?phone=5511994227774&text=Ol%C3%A1%20Dra.%20Valdizia%2C%20vim%20pelo%20seu%20site%20e%20gostaria%20de%20tirar%20algumas%20d%C3%BAvidas.&type=phone_number&app_absent=0';
@endphp

<section id="agendamento" class="bg-verde px-6 py-16 lg:px-10 lg:py-20">
    <div class="mx-auto grid max-w-7xl grid-cols-1 items-center gap-10 lg:grid-cols-12">
        <div class="lg:col-span-7">
            <p class="eyebrow text-dourado">Vamos conversar?</p>
            <h2 class="mt-4 font-serif text-3xl leading-tight font-semibold text-white sm:text-4xl">
                Agende sua consulta inicial
            </h2>
            <p class="mt-4 max-w-lg text-sm leading-relaxed text-white/75 sm:text-base">
                Estou à disposição para entender o seu caso e indicar o melhor caminho jurídico.
            </p>
        </div>

        <div class="flex flex-col items-start gap-4 lg:col-span-5 lg:items-end">
            <a href="{{ route('agendamento.create') }}" class="btn-gold">
                <x-ui.icon name="calendar" class="h-4 w-4" />
                Clique aqui e agende sua consulta
                <x-ui.icon name="arrow-right" class="h-4 w-4" />
            </a>

            <a href="{{ $whatsapp }}" target="_blank" rel="noopener" class="inline-flex items-center gap-1.5 text-xs font-medium text-white/70 transition-colors hover:text-dourado">
                <x-ui.icon name="whatsapp" class="h-3.5 w-3.5" />
                ou fale direto pelo WhatsApp
            </a>

            <div class="mt-2 flex flex-col gap-3 sm:flex-row sm:gap-6">
                <span class="flex items-center gap-2 text-xs font-medium text-white/70">
                    <x-ui.icon name="user" class="h-4 w-4 text-dourado" />
                    Atendimento presencial e online
                </span>
                <span class="flex items-center gap-2 text-xs font-medium text-white/70">
                    <x-ui.icon name="shield-check" class="h-4 w-4 text-dourado" />
                    Seus dados em segurança
                </span>
            </div>
        </div>
    </div>
</section>
