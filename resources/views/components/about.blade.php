@php
    $valores = [
        ['icon' => 'scale', 'label' => 'Ética em todas as relações'],
        ['icon' => 'handshake', 'label' => 'Atendimento humanizado'],
        ['icon' => 'shield-check', 'label' => 'Dedicação a cada caso'],
    ];
@endphp

<section id="sobre" class="bg-bege/40">
    <div class="mx-auto grid max-w-7xl grid-cols-1 lg:grid-cols-12">
        {{--
            Composição decorativa (CSS/SVG) com os valores do escritório.
            Pode ser substituída por uma fotografia de ambiente.
        --}}
        <div class="relative flex min-h-[20rem] items-center justify-center bg-verde-escuro px-10 py-16 lg:col-span-5 lg:min-h-0">
            <div class="w-full max-w-[15rem]">
                <x-ui.icon name="scale" class="mx-auto h-10 w-10 text-dourado" />
                <ul class="mt-8 space-y-2">
                    @foreach (['Ética', 'Respeito', 'Dedicação', 'Transparência'] as $palavra)
                        <li class="rounded-md border border-dourado/30 bg-white/5 px-4 py-3 text-center text-xs font-semibold tracking-[0.2em] text-white/85 uppercase">
                            {{ $palavra }}
                        </li>
                    @endforeach
                </ul>
            </div>
        </div>

        {{-- Texto --}}
        <div class="flex flex-col justify-center px-6 py-16 lg:col-span-7 lg:px-16 lg:py-20">
            <p class="eyebrow text-dourado-texto">Sobre a Dra. Valdizia Melo</p>
            <h2 class="mt-4 font-serif text-3xl leading-tight font-semibold text-texto sm:text-4xl">
                Advocacia com escuta, responsabilidade e compromisso com cada história
            </h2>

            {{-- Texto institucional oficial. --}}
            <div class="mt-6 max-w-xl space-y-4 text-base leading-relaxed text-texto/70">
                <p>
                    Dra. Valdizia Melo é advogada com atuação nas áreas de Direito Previdenciário,
                    Cível e de Família, oferecendo atendimento jurídico pautado pela escuta atenta,
                    clareza e responsabilidade profissional.
                </p>
                <p>
                    Seu trabalho parte da compreensão de que, por trás de cada questão jurídica,
                    existe uma pessoa, uma família ou uma decisão importante. Por isso, busca
                    compreender cada situação de forma individualizada, apresentando caminhos
                    jurídicos de maneira clara e responsável.
                </p>
                <p>
                    Sua atuação também é marcada pelo compromisso com a valorização da advocacia e,
                    especialmente, com o fortalecimento da participação feminina na profissão.
                    Integra a Comissão de Mulheres Advogadas, espaço voltado ao fortalecimento da
                    advocacia feminina, ao aprendizado e à valorização das mulheres na área jurídica.
                </p>
                <p>
                    Atendimento profissional, próximo e responsável, desde a compreensão inicial da
                    situação até a orientação sobre os caminhos jurídicos adequados para cada caso.
                </p>
            </div>

            {{-- Destaque da Comissão de Mulheres Advogadas. --}}
            <div class="mt-8 max-w-xl rounded-2xl border border-dourado/25 bg-dourado/5 px-5 py-4">
                <p class="text-xs font-semibold tracking-[0.15em] text-dourado-texto uppercase">
                    Compromisso com a advocacia feminina
                </p>
                <p class="mt-2 text-sm leading-relaxed text-texto/75">
                    Participação na
                    <a href="https://www.instagram.com/mulheresadvogadas_oabpenha/" target="_blank" rel="noopener" class="font-medium text-dourado-texto underline underline-offset-2">Comissão de Mulheres Advogadas</a>,
                    contribuindo para um espaço de fortalecimento, aprendizado e valorização das
                    mulheres na advocacia.
                </p>
            </div>

            <a href="/#agendamento" class="btn-verde mt-8 w-fit">
                Conheça mais sobre o escritório
                <x-ui.icon name="arrow-right" class="h-4 w-4" />
            </a>

            <ul class="mt-10 grid grid-cols-1 gap-5 sm:grid-cols-3">
                @foreach ($valores as $valor)
                    <li class="flex items-start gap-3">
                        <x-ui.icon :name="$valor['icon']" class="mt-0.5 h-5 w-5 shrink-0 text-dourado" />
                        <span class="text-sm leading-snug font-medium text-texto/80">{{ $valor['label'] }}</span>
                    </li>
                @endforeach
            </ul>
        </div>
    </div>
</section>
