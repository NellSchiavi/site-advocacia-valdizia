<section class="relative overflow-hidden bg-verde">
    {{--
        Fotografia profissional como camada de fundo do Hero, em 3 camadas:
          1) a foto, absoluta, ancorada à direita;
          2) um overlay com gradiente horizontal — opaco (#0f2d27, mesmo tom
             do bg-verde) à esquerda, transparente à direita;
          3) o conteúdo (texto/CTA/citação), acima, com z-10.

        Oculta abaixo de lg (< 1024px): até esse ponto a grade tem coluna
        única e o gradiente ficaria sobre o texto, prejudicando a
        legibilidade. Em mobile e tablet o Hero usa o fundo verde sólido.

        O arquivo atual é pequeno (150×150); substituí-lo por uma versão em
        alta resolução no mesmo caminho (public/imagens/
        Foto-profissional-Valdizia.jpg) melhora a nitidez sem outras mudanças.
    --}}
    <div class="absolute inset-0 hidden lg:block" aria-hidden="true">
        <img
            src="{{ asset('imagens/Foto-profissional-Valdizia.jpg') }}"
            alt=""
            width="150"
            height="150"
            class="absolute inset-y-0 right-0 h-full w-[44%] object-cover object-[70%_18%]"
        >
        <div
            class="absolute inset-0"
            style="background: linear-gradient(to right, #0f2d27 0%, #0f2d27 36%, rgba(15,45,39,0.88) 52%, rgba(15,45,39,0.5) 68%, rgba(15,45,39,0.15) 84%, rgba(15,45,39,0) 100%);"
        ></div>
    </div>

    {{--
        A partir de 2xl (>=1536px) o container passa de max-w-7xl para 90% do
        viewport. A camada de foto acima acompanha a largura total da tela;
        com o container fixo em 1280px, a citação se afastaria da foto em
        monitores grandes. A largura percentual mantém a proporção
        citação/foto em qualquer resolução.
    --}}
    <div class="relative z-10 mx-auto grid max-w-7xl grid-cols-1 items-stretch lg:grid-cols-12 2xl:w-[90%] 2xl:max-w-none">
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

        {{-- Coluna direita: só ancora a citação/assinatura na base — o
             espaço acima dela é transparente, revelando a foto+overlay
             (camadas 1 e 2) por trás. --}}
        <div class="relative flex flex-col justify-end lg:col-span-6 xl:col-span-5">
            {{-- Em 2xl a citação tem largura máxima e fica alinhada à
                 direita, sobre a foto. --}}
            <blockquote class="border-t border-white/10 bg-verde-escuro/60 px-6 py-8 lg:px-10 2xl:max-w-xl 2xl:ml-auto">
                <p class="font-serif text-lg leading-snug text-white/90 italic">
                    &ldquo;Direito é instrumento de justiça e de novas oportunidades.&rdquo;
                </p>
                <footer class="mt-3 text-xs font-semibold tracking-[0.15em] text-dourado uppercase">
                    Dra. Valdizia Melo — Advogada
                    <span class="block normal-case tracking-normal text-white/60">OAB/SP 542.344</span>
                </footer>
            </blockquote>
        </div>
    </div>
</section>
