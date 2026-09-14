@php
    $navLinks = [
        ['label' => 'Início', 'href' => '/#top'],
        ['label' => 'Sobre', 'href' => '/#sobre'],
        ['label' => 'Áreas de Atuação', 'href' => '/#areas-de-atuacao'],
        ['label' => 'Agendamento', 'href' => route('agendamento.create')],
        ['label' => 'Contato', 'href' => '/#contato'],
    ];
    // Nomes apresentados ao visitante (Fase 5) — ver AreaAtuacao::nomePublico().
    $areasRodape = ['Direito Previdenciário', 'Direito Cível', 'Direito de Família'];
    $whatsapp = 'https://api.whatsapp.com/send/?phone=5511994227774&text=Ol%C3%A1%20Dra.%20Valdizia%2C%20vim%20pelo%20seu%20site%20e%20gostaria%20de%20tirar%20algumas%20d%C3%BAvidas.&type=phone_number&app_absent=0';
    $instagram = 'https://www.instagram.com/valdiziamello/';
@endphp

<footer id="contato" class="border-t border-bege bg-offwhite">
    <div class="mx-auto max-w-7xl px-6 py-14 lg:px-10">
        <div class="grid grid-cols-1 gap-10 sm:grid-cols-2 lg:grid-cols-12">
            {{-- Identidade --}}
            <div class="lg:col-span-3">
                <a href="/#top" class="flex items-center gap-3">
                    <span class="flex h-11 w-11 items-center justify-center rounded-full border border-dourado-texto/60 font-serif text-lg font-semibold text-dourado-texto">
                        VM
                    </span>
                    <span class="leading-tight">
                        <span class="block font-serif text-base font-semibold text-texto">Dra. Valdizia Melo</span>
                        <span class="block text-[0.65rem] font-medium tracking-[0.18em] text-verde uppercase">
                            Advocacia e Assessoria Jurídica
                        </span>
                    </span>
                </a>
                <p class="mt-4 text-xs font-medium text-texto/60">OAB/SP 542.344</p>
            </div>

            {{-- Áreas de atuação --}}
            <div class="lg:col-span-3">
                <p class="eyebrow text-dourado-texto">Áreas de Atuação</p>
                <ul class="mt-4 space-y-2.5 text-sm text-texto/70">
                    @foreach ($areasRodape as $areaRodape)
                        <li>{{ $areaRodape }}</li>
                    @endforeach
                </ul>
            </div>

            {{-- Navegação --}}
            <nav aria-label="Navegação do rodapé" class="lg:col-span-3">
                <p class="eyebrow text-dourado-texto">Navegação</p>
                <ul class="mt-4 space-y-2.5">
                    @foreach ($navLinks as $link)
                        <li>
                            <a href="{{ $link['href'] }}" class="text-sm text-texto/70 transition-colors hover:text-verde">
                                {{ $link['label'] }}
                            </a>
                        </li>
                    @endforeach
                </ul>
            </nav>

            {{-- Contato --}}
            <div class="lg:col-span-3">
                <p class="eyebrow text-dourado-texto">Contato</p>
                <ul class="mt-4 space-y-2.5 text-sm text-texto/70">
                    <li>
                        <a href="mailto:adv.valdizia@gmail.com" class="transition-colors hover:text-verde">
                            adv.valdizia@gmail.com
                        </a>
                    </li>
                    <li>
                        <a href="{{ $whatsapp }}" target="_blank" rel="noopener" class="transition-colors hover:text-verde">
                            WhatsApp: (11) 99422-7774
                        </a>
                    </li>
                    <li class="pt-1 not-italic">
                        Av. Quinze de Novembro, 58, Sala 3<br>
                        Centro — Ferraz de Vasconcelos/SP
                    </li>
                </ul>

                <div class="mt-5 flex items-center gap-3">
                    <a href="{{ $whatsapp }}" target="_blank" rel="noopener"
                       class="flex h-10 w-10 items-center justify-center rounded-full bg-verde text-white transition-colors hover:bg-verde-escuro"
                       aria-label="Conversar no WhatsApp">
                        <x-ui.icon name="whatsapp" class="h-4.5 w-4.5" />
                    </a>
                    <a href="{{ $instagram }}" target="_blank" rel="noopener"
                       class="flex h-10 w-10 items-center justify-center rounded-full bg-verde text-white transition-colors hover:bg-verde-escuro"
                       aria-label="Perfil no Instagram (@valdiziamello)">
                        <x-ui.icon name="instagram" class="h-4.5 w-4.5" />
                    </a>
                </div>
            </div>
        </div>

        <div class="mt-12 flex flex-col gap-3 border-t border-bege pt-6 text-xs text-texto/70 sm:flex-row sm:items-center sm:justify-between">
            <p>&copy; {{ now()->year }} NS | Nelson Schiavi Soluções Digitais.</p>
            <p class="flex flex-wrap gap-x-2">
                <a href="{{ route('politica-privacidade') }}" class="underline underline-offset-2 hover:text-verde">Política de Privacidade</a>
                <span aria-hidden="true">&middot;</span>
                <a href="{{ route('agendamento.create') }}" class="underline underline-offset-2 hover:text-verde">Agendamento</a>
            </p>
        </div>
    </div>
</footer>
