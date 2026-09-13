@props(['name', 'class' => 'h-6 w-6'])

{{--
    Ícones de linha fina centralizados num único componente — evita duplicar
    markup SVG espalhado pelas seções e mantém o estilo minimalista da
    referência (stroke fino, sem preenchimento, sem clichês jurídicos).
--}}
<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5"
     stroke="currentColor" aria-hidden="true" {{ $attributes->merge(['class' => $class]) }}>
    @switch($name)
        @case('shield-check')
            <path stroke-linecap="round" stroke-linejoin="round" d="M12 3.5 5 6v5.2c0 4.3 2.9 7.6 7 8.8 4.1-1.2 7-4.5 7-8.8V6l-7-2.5Z" />
            <path stroke-linecap="round" stroke-linejoin="round" d="m9.25 12 1.9 1.9 3.6-3.9" />
            @break

        @case('document-text')
            <path stroke-linecap="round" stroke-linejoin="round" d="M8 4h6l4 4v11a1 1 0 0 1-1 1H8a1 1 0 0 1-1-1V5a1 1 0 0 1 1-1Z" />
            <path stroke-linecap="round" stroke-linejoin="round" d="M13.5 4v4h4M9 13h6M9 16.5h4" />
            @break

        @case('handshake')
            <path stroke-linecap="round" stroke-linejoin="round" d="m3 11 3.2-3.2a2 2 0 0 1 2.6-.2l2.2 1.7M21 11l-3.2-3.2a2 2 0 0 0-2.6-.2L9 12l2.4 2.1a1.6 1.6 0 0 0 2.2-.1l.4-.4" />
            <path stroke-linecap="round" stroke-linejoin="round" d="m6 10-2.5 2.5a1.4 1.4 0 0 0 0 2l3 3a1.4 1.4 0 0 0 2 0L9 17M18 10l2.5 2.5a1.4 1.4 0 0 1 0 2l-3 3a1.4 1.4 0 0 1-2 0L15 17" />
            @break

        @case('chart-bar')
            <path stroke-linecap="round" stroke-linejoin="round" d="M4 20V10M10 20V4M16 20v-7M22 20H2" />
            @break

        @case('user')
            <path stroke-linecap="round" stroke-linejoin="round" d="M12 12a4 4 0 1 0 0-8 4 4 0 0 0 0 8Z" />
            <path stroke-linecap="round" stroke-linejoin="round" d="M4.5 20.5c1.2-3.6 4.1-5.5 7.5-5.5s6.3 1.9 7.5 5.5" />
            @break

        @case('users')
            <path stroke-linecap="round" stroke-linejoin="round" d="M9 12a3.5 3.5 0 1 0 0-7 3.5 3.5 0 0 0 0 7Z" />
            <path stroke-linecap="round" stroke-linejoin="round" d="M2.8 19.5c1-3 3.4-4.7 6.2-4.7s5.2 1.7 6.2 4.7" />
            <path stroke-linecap="round" stroke-linejoin="round" d="M15.5 8.3a2.7 2.7 0 1 1 3.3 2.6M16.8 14.9c2.2.3 4 1.8 4.7 4.6" />
            @break

        @case('scale')
            <path stroke-linecap="round" stroke-linejoin="round" d="M12 3v18M8 21h8M6 7h12M6 7l-3.5 6.5a3.2 3.2 0 0 0 7 0L6 7ZM18 7l-3.5 6.5a3.2 3.2 0 0 0 7 0L18 7Z" />
            @break

        @case('calendar')
            <path stroke-linecap="round" stroke-linejoin="round" d="M7 3.5V6M17 3.5V6M4 9h16M5 6h14a1 1 0 0 1 1 1v12a1 1 0 0 1-1 1H5a1 1 0 0 1-1-1V7a1 1 0 0 1 1-1Z" />
            @break

        @case('arrow-right')
            <path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12h15M13.5 5.5 19.5 12l-6 6.5" />
            @break

        @case('whatsapp')
            <path stroke-linecap="round" stroke-linejoin="round" d="M7 18.2 4 20l1.9-3.4A7.9 7.9 0 1 1 12 20a7.9 7.9 0 0 1-5-1.8Z" />
            <path stroke-linecap="round" stroke-linejoin="round" d="M9.2 9.3c.2-.5.4-.5.7-.5h.5c.2 0 .4 0 .6.4.2.5.6 1.4.6 1.5.1.1.1.3 0 .4-.1.2-.2.3-.3.4l-.4.4c-.1.1-.2.3-.1.5.3.5.8 1.1 1.4 1.6.6.5 1.2.8 1.7 1 .2.1.3 0 .4-.1l.5-.6c.1-.2.3-.2.5-.1l1.4.7c.2.1.3.2.4.3.1.2.1.9-.2 1.3-.3.4-1.1.9-1.9.9-.6 0-2.4-.3-4-1.8-1.9-1.8-2.6-3.6-2.7-3.9-.1-.3-.6-1.2-.6-2 0-.9.4-1.3.6-1.4Z" />
            @break

        @case('instagram')
            <rect x="4" y="4" width="16" height="16" rx="4.5" stroke-linecap="round" stroke-linejoin="round" />
            <circle cx="12" cy="12" r="3.4" stroke-linecap="round" stroke-linejoin="round" />
            <circle cx="16.6" cy="7.4" r="0.4" fill="currentColor" stroke="none" />
            @break

        @case('menu')
            <path stroke-linecap="round" stroke-linejoin="round" d="M4 7h16M4 12h16M4 17h16" />
            @break

        @case('close')
            <path stroke-linecap="round" stroke-linejoin="round" d="M6 6l12 12M18 6 6 18" />
            @break

        @case('clock')
            <circle cx="12" cy="12" r="8.5" stroke-linecap="round" stroke-linejoin="round" />
            <path stroke-linecap="round" stroke-linejoin="round" d="M12 7.5V12l3 2" />
            @break

        @case('flag')
            <path stroke-linecap="round" stroke-linejoin="round" d="M6 3.5v17M6 4.5h10.5l-2.3 3.5 2.3 3.5H6" />
            @break

        @case('settings')
            <circle cx="12" cy="12" r="2.8" stroke-linecap="round" stroke-linejoin="round" />
            <path stroke-linecap="round" stroke-linejoin="round" d="M12 3.5v2.3M12 18.2v2.3M4.6 7.2l2 1.15M17.4 15.65l2 1.15M4.6 16.8l2-1.15M17.4 8.35l2-1.15" />
            @break

        @case('logout')
            <path stroke-linecap="round" stroke-linejoin="round" d="M9 4.5H6a1.5 1.5 0 0 0-1.5 1.5v12A1.5 1.5 0 0 0 6 19.5h3M15.5 16l4-4-4-4M19 12H9" />
            @break
    @endswitch
</svg>
