@php
    $navLinks = [
        ['label' => 'Início', 'href' => '/#top'],
        ['label' => 'Sobre', 'href' => '/#sobre'],
        ['label' => 'Áreas de Atuação', 'href' => '/#areas-de-atuacao'],
        ['label' => 'Agendamento', 'href' => route('agendamento.create')],
        ['label' => 'Contato', 'href' => '/#contato'],
    ];
@endphp

<header id="top" x-data="{ open: false }" class="sticky top-0 z-40 border-b border-bege/70 bg-offwhite/95 backdrop-blur">
    <div class="mx-auto flex max-w-7xl items-center justify-between gap-6 px-6 py-4 lg:px-10">
        {{-- Identidade --}}
        <a href="/#top" class="flex items-center gap-3">
            <span class="flex h-11 w-11 items-center justify-center rounded-full border border-dourado-texto/60 font-serif text-lg font-semibold text-dourado-texto">
                VM
            </span>
            <span class="leading-tight">
                <span class="block font-serif text-base font-semibold tracking-wide text-texto sm:text-lg">
                    Dra. Valdizia Melo
                </span>
                <span class="block text-[0.65rem] font-medium tracking-[0.18em] text-verde uppercase">
                    Advocacia e Assessoria Jurídica
                </span>
            </span>
        </a>

        {{-- Navegação desktop --}}
        <nav aria-label="Navegação principal" class="hidden items-center gap-8 lg:flex">
            @foreach ($navLinks as $link)
                <a href="{{ $link['href'] }}" class="text-sm font-medium text-texto/80 transition-colors hover:text-verde">
                    {{ $link['label'] }}
                </a>
            @endforeach
        </nav>

        <div class="hidden lg:block">
            <a href="{{ route('agendamento.create') }}" class="btn-verde">
                <x-ui.icon name="calendar" class="h-4 w-4" />
                Agende sua consulta
                <x-ui.icon name="arrow-right" class="h-4 w-4" />
            </a>
        </div>

        {{-- Botão do menu mobile --}}
        <button
            type="button"
            class="inline-flex items-center justify-center rounded-full border border-verde/20 p-2.5 text-verde lg:hidden"
            @click="open = !open"
            :aria-expanded="open.toString()"
            aria-controls="menu-mobile"
        >
            <span class="sr-only">Abrir menu de navegação</span>
            <x-ui.icon name="menu" x-show="!open" class="h-5 w-5" />
            <x-ui.icon name="close" x-show="open" x-cloak class="h-5 w-5" />
        </button>
    </div>

    {{-- Navegação mobile --}}
    <nav
        id="menu-mobile"
        x-show="open"
        x-cloak
        x-transition:enter="transition ease-out duration-150"
        x-transition:enter-start="opacity-0 -translate-y-1"
        x-transition:enter-end="opacity-100 translate-y-0"
        @click.outside="open = false"
        aria-label="Navegação mobile"
        class="border-t border-bege/70 bg-offwhite lg:hidden"
    >
        <ul class="flex flex-col gap-1 px-6 py-4">
            @foreach ($navLinks as $link)
                <li>
                    <a href="{{ $link['href'] }}" @click="open = false" class="block rounded-md px-2 py-2.5 text-sm font-medium text-texto/80 hover:bg-bege/50 hover:text-verde">
                        {{ $link['label'] }}
                    </a>
                </li>
            @endforeach
            <li class="pt-2">
                <a href="{{ route('agendamento.create') }}" class="btn-verde w-full justify-center">
                    <x-ui.icon name="calendar" class="h-4 w-4" />
                    Agende sua consulta
                    <x-ui.icon name="arrow-right" class="h-4 w-4" />
                </a>
            </li>
        </ul>
    </nav>
</header>
