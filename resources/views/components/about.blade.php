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
            Placeholder ilustrativo (CSS/SVG) no lugar da fotografia de
            ambiente da referência (estatueta + livros). Nenhuma imagem real
            foi inventada — é só uma composição decorativa com os valores do
            escritório, substituível por uma fotografia real no futuro.
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
                Compromisso com pessoas e com histórias reais
            </h2>

            {{--
                Texto institucional provisório: descreve apenas o que já foi
                formalmente definido para o projeto (áreas de atuação e
                valores). Não inclui formação, tempo de atuação, número de
                clientes ou qualquer dado não fornecido pela cliente — a
                biografia definitiva deve ser enviada por ela para substituir
                este parágrafo.
            --}}
            <p class="mt-6 max-w-xl text-base leading-relaxed text-texto/70">
                A Dra. Valdizia Melo é advogada, atuando com dedicação nas áreas
                Previdenciária, Cível e de Família. Seu trabalho é pautado pela ética,
                pela transparência e pelo atendimento humanizado, sempre buscando o
                caminho jurídico mais adequado para a realidade de cada cliente.
            </p>

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
