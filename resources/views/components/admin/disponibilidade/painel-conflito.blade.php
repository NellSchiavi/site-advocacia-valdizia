@props(['afetadas'])
<div class="mt-4 rounded-lg border border-red-300 bg-red-50 p-4" role="alert">
    <p class="text-sm font-semibold text-red-800">
        Atenção: já existe(m) {{ count($afetadas) }} consulta(s) agendada(s) neste período.
    </p>
    <ul class="mt-2 space-y-1 text-sm text-red-800">
        @foreach ($afetadas as $consultaAfetada)
            <li>
                {{ \Carbon\CarbonImmutable::parse($consultaAfetada['data'])->format('d/m/Y') }}
                — {{ $consultaAfetada['hora_inicio'] }} às {{ $consultaAfetada['hora_fim'] }}
                — {{ $consultaAfetada['nome_cliente'] }}
            </li>
        @endforeach
    </ul>
    <p class="mt-2 text-sm text-red-800">
        Se você continuar, essa(s) consulta(s) será(ão) cancelada(s) e os clientes serão notificados por e-mail.
        Esta ação não pode ser desfeita.
    </p>
    <button
        type="submit" name="confirmar_cancelamento" value="1"
        class="mt-3 rounded-full bg-red-600 px-4 py-2 text-sm font-semibold text-white transition-colors hover:bg-red-700"
    >
        Sim, confirmar e cancelar {{ count($afetadas) }} consulta(s)
    </button>
</div>
