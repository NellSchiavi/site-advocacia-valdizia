@props(['title' => null, 'description' => null])
<!DOCTYPE html>
<html lang="pt-BR" class="scroll-smooth">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="{{ $description ?? 'Dra. Valdizia Melo — Advocacia e Assessoria Jurídica nas áreas Previdenciária, Cível e de Família, com atendimento humanizado e personalizado.' }}">
    <meta name="theme-color" content="#0F2D27">

    <title>{{ $title ?? 'Dra. Valdizia Melo — Advocacia e Assessoria Jurídica' }}</title>

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
