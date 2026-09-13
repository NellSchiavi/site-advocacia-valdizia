@php
    $navLinks = [
        ['label' => 'Início', 'href' => '/#top'],
        ['label' => 'Sobre', 'href' => '/#sobre'],
        ['label' => 'Áreas de Atuação', 'href' => '/#areas-de-atuacao'],
        ['label' => 'Agendamento', 'href' => route('agendamento.create')],
        ['label' => 'Contato', 'href' => '/#contato'],
    ];
    $whatsapp = 'https://api.whatsapp.com/send/?phone=5511994227774&text=Ol%C3%A1%20Dra.%20Valdizia%2C%20vim%20pelo%20seu%20site%20e%20gostaria%20de%20tirar%20algumas%20d%C3%BAvidas.&type=phone_number&app_absent=0';
    $instagram = 'https://www.instagram.com/valdiziamello/';
@endphp

<footer id="contato" class="border-t border-bege bg-offwhite">
    <div class="mx-auto max-w-7xl px-6 py-14 lg:px-10">
        <div class="grid grid-cols-1 gap-10 lg:grid-cols-12">
            {{-- Identidade --}}
            <div class="lg:col-span-4">
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
                <p class="mt-6 max-w-xs font-serif text-sm text-texto/60 italic">
                    Direito hoje, mais tranquilidade amanhã.
                </p>
            </div>

            {{-- Navegação --}}
            <nav aria-label="Navegação do rodapé" class="lg:col-span-4">
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
            <div class="lg:col-span-4">
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
            <p>&copy; {{ now()->year }} Dra. Valdizia Melo. Todos os direitos reservados.</p>
            {{-- Termos de Uso ainda não existe como página nesta fase — texto
                 informativo, sem link quebrado, até ser publicada. --}}
            <p>
                <a href="{{ route('politica-privacidade') }}" class="underline underline-offset-2 hover:text-verde">Política de Privacidade</a>
                &middot; Termos de Uso <span class="italic">(em breve)</span>
            </p>
        </div>
    </div>
</footer>
