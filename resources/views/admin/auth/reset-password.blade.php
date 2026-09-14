<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <meta name="theme-color" content="#0F2D27">

    <title>Redefinir senha — Painel administrativo — Dra. Valdizia Melo</title>

    @fonts
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="flex min-h-screen items-center justify-center bg-verde px-6 py-16 font-sans text-texto antialiased">
    <main id="conteudo-principal" class="w-full max-w-sm">
        <div class="mb-8 text-center">
            <span class="mx-auto flex h-12 w-12 items-center justify-center rounded-full border border-dourado/60 font-serif text-lg font-semibold text-dourado">
                VM
            </span>
            <h1 class="mt-4 font-serif text-xl font-semibold text-white">Redefinir senha</h1>
            <p class="mt-1 text-sm text-offwhite/70">Escolha uma nova senha para sua conta</p>
        </div>

        <div class="rounded-2xl border border-white/10 bg-offwhite p-6 shadow-lg sm:p-8">
            @error('email')
                <p class="mb-5 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800" role="alert">
                    {{ $message }}
                </p>
            @enderror

            <form method="POST" action="{{ route('admin.password.update') }}" novalidate>
                @csrf
                <input type="hidden" name="token" value="{{ $token }}">

                <div>
                    <label for="email" class="text-sm font-medium text-texto">E-mail</label>
                    <input
                        type="email" id="email" name="email" value="{{ old('email', $email) }}"
                        autocomplete="username" autofocus required
                        aria-required="true"
                        class="mt-1.5 w-full rounded-lg border border-bege bg-white px-4 py-2.5 text-sm text-texto focus:border-dourado focus:ring-1 focus:ring-dourado focus:outline-none"
                    >
                </div>

                <div class="mt-5">
                    <label for="password" class="text-sm font-medium text-texto">Nova senha</label>
                    <input
                        type="password" id="password" name="password"
                        autocomplete="new-password" required
                        aria-required="true"
                        @if ($errors->has('password')) aria-invalid="true" aria-describedby="erro-password" @endif
                        class="mt-1.5 w-full rounded-lg border border-bege bg-white px-4 py-2.5 text-sm text-texto focus:border-dourado focus:ring-1 focus:ring-dourado focus:outline-none"
                    >
                    @error('password')
                        <p id="erro-password" class="mt-1.5 text-xs font-medium text-red-700" role="alert">{{ $message }}</p>
                    @enderror
                </div>

                <div class="mt-5">
                    <label for="password_confirmation" class="text-sm font-medium text-texto">Confirmar nova senha</label>
                    <input
                        type="password" id="password_confirmation" name="password_confirmation"
                        autocomplete="new-password" required
                        aria-required="true"
                        class="mt-1.5 w-full rounded-lg border border-bege bg-white px-4 py-2.5 text-sm text-texto focus:border-dourado focus:ring-1 focus:ring-dourado focus:outline-none"
                    >
                </div>

                <button type="submit" class="btn-gold mt-7 w-full justify-center">
                    Redefinir senha
                </button>
            </form>
        </div>

        <p class="mt-6 text-center text-xs text-offwhite/60">
            <a href="{{ route('admin.login') }}" class="underline underline-offset-2 hover:text-offwhite">Voltar para o login</a>
        </p>
    </main>
</body>
</html>
