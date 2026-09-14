@php
    // Ordem canônica usada em todo o projeto (Fase 0 e Fase 2): Previdenciário, Cível, Família.
    // Nomes apresentados ao visitante (Fase 5): "Direito Cível" na
    // comunicação pública — o nome interno "Direito Civil" (banco/painel
    // administrativo) permanece inalterado, ver AreaAtuacao::nomePublico().
    $areas = [
        [
            'icon' => 'user',
            'titulo' => 'Direito Previdenciário',
            'descricao' => 'Aposentadorias, benefícios do INSS, auxílio-doença e revisão de benefícios.',
        ],
        [
            'icon' => 'document-text',
            'titulo' => 'Direito Cível',
            'descricao' => 'Contratos, indenizações, responsabilidade civil e direitos do consumidor.',
        ],
        [
            'icon' => 'users',
            'titulo' => 'Direito de Família',
            'descricao' => 'Divórcio, pensão alimentícia, guarda, regulamentação de visitas e inventário.',
        ],
    ];
@endphp

<section id="areas-de-atuacao" class="bg-offwhite px-6 py-20 lg:px-10 lg:py-28">
    <div class="mx-auto max-w-7xl">
        <div class="grid grid-cols-1 gap-8 lg:grid-cols-12 lg:items-end">
            <div class="lg:col-span-7">
                <p class="eyebrow text-dourado-texto">Áreas de Atuação</p>
                <h2 class="mt-4 font-serif text-3xl leading-tight font-semibold text-texto sm:text-4xl">
                    Atuação em Direito Previdenciário, Cível e de Família
                </h2>
            </div>
            <div class="flex flex-col gap-4 lg:col-span-5 lg:items-end lg:text-right">
                <p class="max-w-sm text-sm leading-relaxed text-texto/70">
                    Atendimento jurídico personalizado, com foco na proteção dos seus
                    direitos e na busca das melhores soluções para cada momento da sua vida.
                </p>
                <a href="#areas-de-atuacao" class="link-arrow text-dourado-texto">
                    Ver todas as áreas
                    <x-ui.icon name="arrow-right" class="h-4 w-4" />
                </a>
            </div>
        </div>

        <div class="mt-12 grid grid-cols-1 gap-6 sm:grid-cols-2 lg:mt-16 lg:grid-cols-3">
            @foreach ($areas as $area)
                <article class="group flex flex-col rounded-2xl border border-bege bg-white p-8 transition-shadow duration-200 hover:shadow-lg hover:shadow-verde/5">
                    <span class="flex h-14 w-14 items-center justify-center rounded-xl bg-verde text-white">
                        <x-ui.icon :name="$area['icon']" class="h-6 w-6" />
                    </span>
                    <h3 class="mt-6 font-serif text-xl font-semibold text-texto">
                        {{ $area['titulo'] }}
                    </h3>
                    <p class="mt-3 text-sm leading-relaxed text-texto/70">
                        {{ $area['descricao'] }}
                    </p>
                    <a href="{{ route('agendamento.create') }}" class="link-arrow mt-6 text-dourado-texto">
                        Saiba mais
                        <x-ui.icon name="arrow-right" class="h-4 w-4" />
                    </a>
                </article>
            @endforeach
        </div>
    </div>
</section>
