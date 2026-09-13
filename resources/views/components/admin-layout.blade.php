@props(['title' => null])
@php
    $itensSidebar = [
        ['label' => 'Dashboard', 'icone' => 'chart-bar', 'rota' => 'admin.dashboard', 'disponivel' => true],
        ['label' => 'Agenda', 'icone' => 'calendar', 'rota' => 'admin.agenda', 'disponivel' => true],
        ['label' => 'Disponibilidade', 'icone' => 'clock', 'disponivel' => false],
        ['label' => 'Feriados', 'icone' => 'flag', 'disponivel' => false],
        ['label' => 'Configurações', 'icone' => 'settings', 'disponivel' => false],
    ];
@endphp
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <meta name="theme-color" content="#0F2D27">

    <title>{{ $title ?? 'Painel' }} — Painel administrativo — Dra. Valdizia Melo</title>

    @fonts
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-offwhite font-sans text-texto antialiased" x-data="{ sidebarAberta: false }">
    <a href="#conteudo-painel" class="sr-only focus:not-sr-only focus:fixed focus:top-2 focus:left-2 focus:z-50 focus:rounded-md focus:bg-verde focus:px-4 focus:py-2 focus:text-sm focus:font-semibold focus:text-white">
        Pular para o conteúdo
    </a>

    <div class="flex min-h-screen">
        {{-- Overlay do menu mobile --}}
        <div
            x-show="sidebarAberta"
            x-cloak
            x-transition:enter="transition ease-out duration-150"
            x-transition:enter-start="opacity-0"
            x-transition:enter-end="opacity-100"
            @click="sidebarAberta = false"
            class="fixed inset-0 z-30 bg-texto/50 lg:hidden"
            aria-hidden="true"
        ></div>

        {{-- Sidebar --}}
        <aside
            id="admin-sidebar"
            :class="sidebarAberta ? 'translate-x-0' : '-translate-x-full'"
            class="fixed inset-y-0 left-0 z-40 flex w-64 shrink-0 flex-col bg-verde text-offwhite transition-transform duration-200 lg:static lg:translate-x-0"
            aria-label="Navegação do painel administrativo"
        >
            <div class="flex h-16 items-center gap-2.5 border-b border-white/10 px-6">
                <span class="flex h-9 w-9 items-center justify-center rounded-full border border-dourado/60 font-serif text-sm font-semibold text-dourado">
                    VM
                </span>
                <span class="font-serif text-base font-semibold tracking-wide">Painel</span>
            </div>

            <nav class="flex-1 space-y-1 px-3 py-4" aria-label="Seções do painel">
                @foreach ($itensSidebar as $item)
                    @if ($item['disponivel'])
                        <a
                            href="{{ route($item['rota']) }}"
                            @if (request()->routeIs($item['rota'])) aria-current="page" @endif
                            class="flex items-center gap-3 rounded-lg px-3 py-2.5 text-sm font-medium transition-colors {{ request()->routeIs($item['rota']) ? 'bg-white/10 text-white' : 'text-offwhite/75 hover:bg-white/5 hover:text-white' }}"
                        >
                            <x-ui.icon :name="$item['icone']" class="h-5 w-5 shrink-0" />
                            {{ $item['label'] }}
                        </a>
                    @else
                        <span
                            class="flex items-center justify-between gap-3 rounded-lg px-3 py-2.5 text-sm font-medium text-offwhite/35"
                            aria-disabled="true"
                        >
                            <span class="flex items-center gap-3">
                                <x-ui.icon :name="$item['icone']" class="h-5 w-5 shrink-0" />
                                {{ $item['label'] }}
                            </span>
                            <span class="shrink-0 rounded-full bg-white/10 px-2 py-0.5 text-[0.6rem] font-semibold tracking-wide text-offwhite/60 uppercase">
                                Em breve
                            </span>
                        </span>
                    @endif
                @endforeach
            </nav>
        </aside>

        {{-- Coluna principal --}}
        <div class="flex min-w-0 flex-1 flex-col">
            <header class="sticky top-0 z-20 flex h-16 items-center justify-between gap-4 border-b border-bege bg-white px-4 lg:px-8">
                <div class="flex items-center gap-3">
                    <button
                        type="button"
                        class="inline-flex items-center justify-center rounded-full border border-bege p-2 text-texto lg:hidden"
                        @click="sidebarAberta = !sidebarAberta"
                        :aria-expanded="sidebarAberta.toString()"
                        aria-controls="admin-sidebar"
                    >
                        <span class="sr-only">Abrir menu do painel</span>
                        <x-ui.icon name="menu" x-show="!sidebarAberta" class="h-5 w-5" />
                        <x-ui.icon name="close" x-show="sidebarAberta" x-cloak class="h-5 w-5" />
                    </button>

                    <div class="leading-tight">
                        <p class="font-serif text-sm font-semibold text-texto sm:text-base">Dra. Valdizia Melo</p>
                        <p class="text-xs text-texto/60">Painel administrativo</p>
                    </div>
                </div>

                <form method="POST" action="{{ route('admin.logout') }}">
                    @csrf
                    <button
                        type="submit"
                        class="inline-flex items-center gap-2 rounded-full border border-bege px-4 py-2 text-sm font-semibold text-texto transition-colors hover:border-dourado hover:bg-bege/30"
                    >
                        <x-ui.icon name="logout" class="h-4 w-4" />
                        Sair
                    </button>
                </form>
            </header>

            <main id="conteudo-painel" class="flex-1 px-4 py-8 lg:px-8">
                {{ $slot }}
            </main>
        </div>
    </div>
</body>
</html>
