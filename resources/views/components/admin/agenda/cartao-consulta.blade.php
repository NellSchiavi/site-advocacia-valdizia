@props(['appointment'])
<button
    type="button"
    @click="abrirDetalhe({{ $appointment->id }})"
    class="flex w-full items-center justify-between gap-3 rounded-lg border border-bege bg-white px-3 py-2.5 text-left text-sm transition-colors hover:border-dourado hover:bg-bege/20 focus-visible:outline-2 focus-visible:outline-dourado"
>
    <span class="min-w-0">
        <span class="block font-semibold text-texto">
            {{ substr($appointment->hora_inicio, 0, 5) }}–{{ substr($appointment->hora_fim, 0, 5) }}
        </span>
        <span class="mt-0.5 block truncate text-texto/80">{{ $appointment->nome_cliente }}</span>
        <span class="block text-xs text-texto/50">{{ $appointment->areaAtuacao->nome ?? '—' }}</span>
    </span>
    <x-admin.status-badge :status="$appointment->status" class="shrink-0" />
</button>
