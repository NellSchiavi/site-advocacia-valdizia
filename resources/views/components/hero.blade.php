<section class="relative overflow-hidden bg-verde">
    {{--
        Fotografia oficial da Dra. Valdizia Melo como CAMADA DE FUNDO do
        Hero (ajuste visual solicitado — substitui a versão anterior com
        mask-image radial/círculo). Composição em 3 camadas, todas dentro
        desta section:
          1) esta foto, absoluta, atrás de tudo, ancorada à direita;
          2) um overlay verde com gradiente HORIZONTAL por cima dela —
             opaco (#0f2d27, mesmo tom institucional do bg-verde) à
             esquerda, esmaecendo até transparente à direita — é o
             overlay que "come" a foto, não uma máscara na própria foto;
          3) o conteúdo (texto/CTA/citação), sempre acima, com z-10.

        Sem círculo, sem moldura, sem card: a foto não tem borda própria,
        só a page's edges (topo/base/direita coincidem com os limites da
        própria section — não há "painel" ao redor dela).

        Escondida abaixo de lg (< 1024px): até esse ponto a grade continua
        em coluna única (grid-cols-1) e o texto/diferenciais ocupam toda a
        largura da section — sem uma coluna própria separando texto da
        foto, o degradê ficaria sobreposto diretamente ao texto, prejudicando
        a legibilidade (problema real encontrado e testado em 834px: a foto
        cobria parte do H1 e dos diferenciais). Alargar a coluna de texto
        para um breakpoint menor entraria em conflito com o layout de 4
        colunas do <x-differentials />, que não deve ser alterado. Por isso
        o efeito só aparece a partir de lg, exatamente onde a grade já vira
        duas colunas reais (texto à esquerda, espaço à direita) — mobile E
        tablet mantêm o Hero 100% verde sólido, como já era antes.

        Arquivo fonte é pequeno (150×150) — cobrir a altura do Hero exige
        upscale (inerente ao efeito pedido, não é ampliação gratuita).
        Ao substituir por uma versão em alta resolução no MESMO caminho
        (public/imagens/Foto-profissional-Valdizia.jpg), a nitidez sobe
        automaticamente sem precisar recriar o efeito.
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
        A partir de 2xl (>=1536px) o container deixa de ficar travado em
        max-w-7xl (1280px fixo) e passa a ser 90% do viewport. Motivo: a
        camada de foto/gradiente acima é ancorada em right-0 da <section>
        (sem max-width) e cresce com a tela inteira; um container preso a
        1280px faz a coluna da citação "descolar" da foto em monitores
        grandes (a foto cresce, o container não). Usar % em vez de um
        max-width maior e fixo mantém a proporção citação/foto consistente
        em qualquer largura grande (1536px, 1920px, 2560px, ...), não só em
        um valor específico. Abaixo de 2xl nada muda — max-w-7xl continua
        exatamente como antes (1440px, tablet e mobile preservados).
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
            {{-- Em 2xl a coluna fica mais larga (container fluido acima); sem
                 um teto a citação esticaria em excesso em monitores muito
                 grandes, então ela é limitada e alinhada à direita da
                 coluna (contra a borda direita, sobre a foto). --}}
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
