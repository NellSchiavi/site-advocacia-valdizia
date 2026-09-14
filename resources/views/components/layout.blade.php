@props(['title' => null, 'description' => null])
@php
    $tituloFinal = $title ?? 'Dra. Valdizia Melo | Advocacia e Assessoria Jurídica';
    $descricaoFinal = $description ?? 'Advocacia e assessoria jurídica em Ferraz de Vasconcelos/SP, com atuação em Direito Previdenciário, Cível e de Família.';
@endphp
<!DOCTYPE html>
<html lang="pt-BR" class="scroll-smooth">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="{{ $descricaoFinal }}">
    <meta name="theme-color" content="#0F2D27">
    <link rel="canonical" href="{{ url()->current() }}">

    <title>{{ $tituloFinal }}</title>

    {{-- Open Graph básico — sem og:image por enquanto: nenhuma imagem de
         compartilhamento foi definida/aprovada pela cliente ainda (a foto
         profissional é retrato, não formato indicado para preview social). --}}
    <meta property="og:type" content="website">
    <meta property="og:site_name" content="Dra. Valdizia Melo — Advocacia e Assessoria Jurídica">
    <meta property="og:locale" content="pt_BR">
    <meta property="og:title" content="{{ $tituloFinal }}">
    <meta property="og:description" content="{{ $descricaoFinal }}">
    <meta property="og:url" content="{{ url()->current() }}">

    @fonts
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="flex min-h-screen flex-col font-sans">
    <a href="#conteudo-principal" class="sr-only focus:not-sr-only focus:fixed focus:top-2 focus:left-2 focus:z-50 focus:rounded-md focus:bg-verde focus:px-4 focus:py-2 focus:text-sm focus:font-semibold focus:text-white">
        Pular para o conteúdo
    </a>

    <x-header />

    <main id="conteudo-principal" class="flex-1">
        {{ $slot }}
    </main>

    <x-footer />
</body>
</html>
