<x-layout title="Política de Privacidade — Dra. Valdizia Melo">
    {{--
        Ponto de integração previsto na Fase 3 (LGPD): o checkbox de
        consentimento do formulário de agendamento precisa de uma página real
        para apontar. O conteúdo jurídico completo da política ainda não foi
        definido/aprovado pela cliente — este é só o placeholder estrutural,
        não a página final.
    --}}
    <section class="mx-auto max-w-3xl px-6 py-20 lg:px-10">
        <p class="eyebrow text-dourado-texto">Privacidade</p>
        <h1 class="mt-4 font-serif text-3xl font-semibold text-texto sm:text-4xl">
            Política de Privacidade
        </h1>
        <p class="mt-6 text-base leading-relaxed text-texto/70">
            Esta página ainda está em construção. Em breve, aqui estará descrito em
            detalhes como os dados enviados pelo formulário de agendamento
            (nome, e-mail, telefone e observação) são utilizados exclusivamente
            para viabilizar o atendimento com a Dra. Valdizia Melo, conforme a
            Lei Geral de Proteção de Dados (LGPD).
        </p>
        <a href="{{ route('home') }}" class="link-arrow mt-8 text-dourado-texto">
            <x-ui.icon name="arrow-right" class="h-4 w-4 rotate-180" />
            Voltar para a página inicial
        </a>
    </section>
</x-layout>
