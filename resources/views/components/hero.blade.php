<section class="relative overflow-hidden bg-verde">
    <div class="mx-auto grid max-w-7xl grid-cols-1 items-stretch lg:grid-cols-12">
        {{-- Coluna de texto --}}
        <div class="flex flex-col justify-center px-6 py-16 sm:py-20 lg:col-span-6 lg:px-10 lg:py-24 xl:col-span-7">
            <p class="eyebrow text-dourado">Direito para a sua tranquilidade</p>

            <h1 class="mt-5 max-w-xl font-serif text-4xl leading-[1.15] font-semibold text-white sm:text-5xl lg:text-[3.25rem]">
                Mais que advocacia,<br class="hidden sm:block">
                soluções para a sua vida.
            </h1>

            <p class="mt-6 max-w-lg text-base leading-relaxed text-white/75 sm:text-lg">
                Advocacia e assessoria jurídica com ética, dedicação e atendimento
                personalizado nas áreas Previdenciária, Cível e de Família.
            </p>

            <div class="mt-10">
                <x-differentials />
            </div>

            <div class="mt-10">
                <a href="{{ route('agendamento.create') }}" class="btn-gold">
                    <x-ui.icon name="calendar" class="h-4 w-4" />
                    Agende sua consulta inicial
                    <x-ui.icon name="arrow-right" class="h-4 w-4" />
                </a>
            </div>
        </div>

        {{-- Coluna de fotografia --}}
        <div class="relative flex flex-col lg:col-span-6 xl:col-span-5">
            {{--
                Placeholder de fotografia: ainda não há uma foto oficial da
                Dra. Valdizia neste projeto. Em respeito à orientação de não
                usar bancos de imagem nem inventar uma pessoa, este painel é
                100% ilustrativo (CSS/SVG), preservando o enquadramento
                retrato e a proporção da referência. Substituir por
                <img src="..." alt="Dra. Valdizia Melo"> assim que a
                fotografia oficial for enviada pela cliente.
            --}}
            <div role="img" aria-label="Espaço reservado para a fotografia oficial da Dra. Valdizia Melo"
                 class="relative min-h-[22rem] flex-1 border-t border-white/10 bg-gradient-to-br from-verde-escuro via-verde to-[#173a32] lg:min-h-0 lg:border-t-0 lg:border-l">
                <div aria-hidden="true" class="absolute inset-0 flex items-center justify-center">
                    <span class="font-serif text-8xl font-semibold text-white/10 select-none">VM</span>
                </div>
                <div aria-hidden="true" class="absolute bottom-5 left-5 right-5 rounded-lg border border-white/15 bg-black/10 px-4 py-3 backdrop-blur-sm">
                    <p class="text-xs font-medium tracking-wide text-white/70">
                        Espaço reservado para fotografia oficial da Dra. Valdizia Melo
                    </p>
                </div>
            </div>

            <blockquote class="border-t border-white/10 bg-verde-escuro/60 px-6 py-8 lg:px-10">
                <p class="font-serif text-lg leading-snug text-white/90 italic">
                    &ldquo;Direito é instrumento de justiça e de novas oportunidades.&rdquo;
                </p>
                <footer class="mt-3 text-xs font-semibold tracking-[0.15em] text-dourado uppercase">
                    Dra. Valdizia Melo — Advogada
                </footer>
            </blockquote>
        </div>
    </div>
</section>
