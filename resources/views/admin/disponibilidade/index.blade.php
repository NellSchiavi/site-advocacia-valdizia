@php
    $meses = ['janeiro', 'fevereiro', 'março', 'abril', 'maio', 'junho', 'julho', 'agosto', 'setembro', 'outubro', 'novembro', 'dezembro'];
    $conflito = session('conflito_pendente');

    $excecaoEmEdicao = request('editar_excecao') ? $excecoes->firstWhere('id', (int) request('editar_excecao')) : null;
    $bloqueioEmEdicao = request('editar_bloqueio') ? $bloqueios->firstWhere('id', (int) request('editar_bloqueio')) : null;
    $feriadoEmEdicao = request('editar_feriado') ? $feriados->firstWhere('id', (int) request('editar_feriado')) : null;
@endphp

<x-admin-layout title="Disponibilidade">
    <h1 class="font-serif text-2xl font-semibold text-texto">Disponibilidade</h1>
    <p class="mt-1 text-sm text-texto/60">
        Configure os horários de atendimento, exceções, feriados e bloqueios. Tudo aqui reflete imediatamente no
        agendamento público.
    </p>

    @if (session('status'))
        <div class="mt-4 rounded-lg border border-verde/25 bg-verde/10 px-4 py-3 text-sm text-verde" role="status">
            {{ session('status') }}
        </div>
    @endif

    @if ($errors->any() && ! $conflito)
        <div class="mt-4 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800" role="alert">
            <ul class="list-disc space-y-1 pl-4">
                @foreach ($errors->all() as $mensagemErro)
                    <li>{{ $mensagemErro }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    {{-- =====================================================
         1. HORÁRIOS SEMANAIS
    ====================================================== --}}
    <section class="mt-8 rounded-2xl border border-bege bg-white p-5 sm:p-6">
        <h2 class="font-serif text-lg font-semibold text-texto">Horários semanais</h2>
        <p class="mt-1 text-sm text-texto/60">Defina o horário de atendimento de cada dia da semana, ou marque como "sem atendimento".</p>

        <div class="mt-5 space-y-3">
            @foreach ($dias as $diaInfo)
                <form method="POST" action="{{ route('admin.disponibilidade.horarios.atualizar', $diaInfo['dia']) }}"
                      class="rounded-xl border border-bege p-4">
                    @csrf
                    <div class="flex flex-wrap items-center gap-4">
                        <span class="w-24 shrink-0 font-semibold text-texto">{{ $diaInfo['nome'] }}</span>

                        <label class="flex items-center gap-2 text-sm text-texto/70">
                            <input type="hidden" name="ativo" value="0">
                            <input type="checkbox" name="ativo" value="1"
                                   @checked(old('ativo', $diaInfo['ativo']))
                                   class="h-4 w-4 rounded border-bege text-verde focus:ring-dourado">
                            Atende neste dia
                        </label>

                        <label class="flex items-center gap-2 text-sm text-texto/70">
                            das
                            <input type="time" name="hora_inicio" value="{{ old('hora_inicio', $diaInfo['hora_inicio']) }}"
                                   class="rounded-lg border border-bege bg-offwhite px-2 py-1.5 text-sm text-texto focus:border-dourado focus:ring-1 focus:ring-dourado focus:outline-none">
                        </label>
                        <label class="flex items-center gap-2 text-sm text-texto/70">
                            às
                            <input type="time" name="hora_fim" value="{{ old('hora_fim', $diaInfo['hora_fim']) }}"
                                   class="rounded-lg border border-bege bg-offwhite px-2 py-1.5 text-sm text-texto focus:border-dourado focus:ring-1 focus:ring-dourado focus:outline-none">
                        </label>

                        <button type="submit" class="ml-auto rounded-full bg-verde px-4 py-1.5 text-sm font-semibold text-white transition-colors hover:bg-verde-escuro">
                            Salvar
                        </button>
                    </div>

                    @if ($conflito && $conflito['tipo'] === 'regra' && $conflito['dia_da_semana'] === $diaInfo['dia'])
                        <x-admin.disponibilidade.painel-conflito :afetadas="$conflito['afetadas']" />
                    @endif
                </form>
            @endforeach
        </div>
    </section>

    {{-- =====================================================
         2. EXCEÇÕES DE HORÁRIO
    ====================================================== --}}
    <section class="mt-6 rounded-2xl border border-bege bg-white p-5 sm:p-6">
        <h2 class="font-serif text-lg font-semibold text-texto">Exceções de horário</h2>
        <p class="mt-1 text-sm text-texto/60">Para uma data específica com horário diferente do padrão semanal (ex.: uma sexta-feira mais curta).</p>

        @if ($excecoes->isEmpty())
            <p class="mt-4 text-sm text-texto/50">Nenhuma exceção cadastrada.</p>
        @else
            <ul class="mt-4 divide-y divide-bege">
                @foreach ($excecoes as $excecao)
                    <li class="flex flex-wrap items-center justify-between gap-3 py-3">
                        <div class="text-sm text-texto">
                            <span class="font-semibold">{{ $excecao->data->format('d/m/Y') }}</span>
                            — {{ substr($excecao->hora_inicio, 0, 5) }} às {{ substr($excecao->hora_fim, 0, 5) }}
                            @if ($excecao->motivo)
                                <span class="text-texto/60">({{ $excecao->motivo }})</span>
                            @endif
                        </div>
                        <div class="flex items-center gap-3 text-sm">
                            <a href="{{ route('admin.disponibilidade.index', ['editar_excecao' => $excecao->id]) }}#form-excecao" class="font-medium text-dourado-texto underline underline-offset-2">Editar</a>
                            <form method="POST" action="{{ route('admin.disponibilidade.excecoes.remover', $excecao) }}" x-data="{ confirmando: false }">
                                @csrf
                                @method('DELETE')
                                <button type="button" x-show="!confirmando" @click="confirmando = true" class="font-medium text-red-700 underline underline-offset-2">Remover</button>
                                <span x-show="confirmando" x-cloak class="inline-flex items-center gap-2">
                                    Remover esta exceção?
                                    <button type="submit" class="font-semibold text-red-700 underline">Sim</button>
                                    <button type="button" @click="confirmando = false" class="text-texto/60 underline">Não</button>
                                </span>
                            </form>
                        </div>
                    </li>
                @endforeach
            </ul>
        @endif

        <form id="form-excecao" method="POST" action="{{ route('admin.disponibilidade.excecoes.salvar') }}" class="mt-5 border-t border-bege pt-5">
            @csrf
            <input type="hidden" name="tipo" value="horario_especial">
            @if ($excecaoEmEdicao)
                <input type="hidden" name="id" value="{{ $excecaoEmEdicao->id }}">
            @endif

            <div class="flex flex-wrap items-end gap-3">
                <div>
                    <label for="excecao_data" class="text-xs font-medium text-texto/60">Data</label>
                    <input type="date" id="excecao_data" name="data"
                           value="{{ old('data', $excecaoEmEdicao?->data?->toDateString()) }}"
                           class="mt-1 block rounded-lg border border-bege bg-offwhite px-3 py-2 text-sm text-texto focus:border-dourado focus:ring-1 focus:ring-dourado focus:outline-none">
                </div>
                <div>
                    <label for="excecao_hora_inicio" class="text-xs font-medium text-texto/60">Das</label>
                    <input type="time" id="excecao_hora_inicio" name="hora_inicio"
                           value="{{ old('hora_inicio', $excecaoEmEdicao ? substr($excecaoEmEdicao->hora_inicio, 0, 5) : null) }}"
                           class="mt-1 block rounded-lg border border-bege bg-offwhite px-3 py-2 text-sm text-texto focus:border-dourado focus:ring-1 focus:ring-dourado focus:outline-none">
                </div>
                <div>
                    <label for="excecao_hora_fim" class="text-xs font-medium text-texto/60">Até</label>
                    <input type="time" id="excecao_hora_fim" name="hora_fim"
                           value="{{ old('hora_fim', $excecaoEmEdicao ? substr($excecaoEmEdicao->hora_fim, 0, 5) : null) }}"
                           class="mt-1 block rounded-lg border border-bege bg-offwhite px-3 py-2 text-sm text-texto focus:border-dourado focus:ring-1 focus:ring-dourado focus:outline-none">
                </div>
                <div class="flex-1">
                    <label for="excecao_motivo" class="text-xs font-medium text-texto/60">Motivo (opcional)</label>
                    <input type="text" id="excecao_motivo" name="motivo" maxlength="255"
                           value="{{ old('motivo', $excecaoEmEdicao?->motivo) }}"
                           class="mt-1 block w-full rounded-lg border border-bege bg-offwhite px-3 py-2 text-sm text-texto focus:border-dourado focus:ring-1 focus:ring-dourado focus:outline-none">
                </div>
                <button type="submit" class="rounded-full bg-verde px-4 py-2 text-sm font-semibold text-white transition-colors hover:bg-verde-escuro">
                    {{ $excecaoEmEdicao ? 'Atualizar' : 'Adicionar' }}
                </button>
            </div>

            @if ($conflito && $conflito['tipo'] === 'excecao')
                <x-admin.disponibilidade.painel-conflito :afetadas="$conflito['afetadas']" />
            @endif
        </form>
    </section>

    {{-- =====================================================
         3. FERIADOS
    ====================================================== --}}
    <section class="mt-6 rounded-2xl border border-bege bg-white p-5 sm:p-6">
        <h2 class="font-serif text-lg font-semibold text-texto">Feriados</h2>
        <p class="mt-1 text-sm text-texto/60">Datas tratadas como indisponíveis para novos agendamentos.</p>

        @if ($feriados->isEmpty())
            <p class="mt-4 text-sm text-texto/50">Nenhum feriado cadastrado.</p>
        @else
            <ul class="mt-4 divide-y divide-bege">
                @foreach ($feriados as $feriado)
                    <li class="flex flex-wrap items-center justify-between gap-3 py-3">
                        <div class="text-sm text-texto">
                            <span class="font-semibold">{{ str_pad($feriado->dia, 2, '0', STR_PAD_LEFT) }}/{{ str_pad($feriado->mes, 2, '0', STR_PAD_LEFT) }}{{ $feriado->ano ? "/{$feriado->ano}" : '' }}</span>
                            — {{ $feriado->nome }}
                            @if ($feriado->recorrente_anual)
                                <span class="text-texto/50">(todo ano)</span>
                            @endif
                        </div>
                        <div class="flex items-center gap-3 text-sm">
                            <a href="{{ route('admin.disponibilidade.index', ['editar_feriado' => $feriado->id]) }}#form-feriado" class="font-medium text-dourado-texto underline underline-offset-2">Editar</a>
                            <form method="POST" action="{{ route('admin.disponibilidade.feriados.remover', $feriado) }}" x-data="{ confirmando: false }">
                                @csrf
                                @method('DELETE')
                                <button type="button" x-show="!confirmando" @click="confirmando = true" class="font-medium text-red-700 underline underline-offset-2">Remover</button>
                                <span x-show="confirmando" x-cloak class="inline-flex items-center gap-2">
                                    Remover este feriado?
                                    <button type="submit" class="font-semibold text-red-700 underline">Sim</button>
                                    <button type="button" @click="confirmando = false" class="text-texto/60 underline">Não</button>
                                </span>
                            </form>
                        </div>
                    </li>
                @endforeach
            </ul>
        @endif

        <form id="form-feriado" method="POST" action="{{ route('admin.disponibilidade.feriados.salvar') }}" class="mt-5 border-t border-bege pt-5">
            @csrf
            @if ($feriadoEmEdicao)
                <input type="hidden" name="id" value="{{ $feriadoEmEdicao->id }}">
            @endif

            <div class="flex flex-wrap items-end gap-3">
                <div class="flex-1">
                    <label for="feriado_nome" class="text-xs font-medium text-texto/60">Nome / descrição</label>
                    <input type="text" id="feriado_nome" name="nome" maxlength="120"
                           value="{{ old('nome', $feriadoEmEdicao?->nome) }}"
                           class="mt-1 block w-full rounded-lg border border-bege bg-offwhite px-3 py-2 text-sm text-texto focus:border-dourado focus:ring-1 focus:ring-dourado focus:outline-none">
                </div>
                <div>
                    <label for="feriado_dia" class="text-xs font-medium text-texto/60">Dia</label>
                    <input type="number" id="feriado_dia" name="dia" min="1" max="31"
                           value="{{ old('dia', $feriadoEmEdicao?->dia) }}"
                           class="mt-1 block w-20 rounded-lg border border-bege bg-offwhite px-3 py-2 text-sm text-texto focus:border-dourado focus:ring-1 focus:ring-dourado focus:outline-none">
                </div>
                <div>
                    <label for="feriado_mes" class="text-xs font-medium text-texto/60">Mês</label>
                    <select id="feriado_mes" name="mes" class="mt-1 block rounded-lg border border-bege bg-offwhite px-3 py-2 text-sm text-texto focus:border-dourado focus:ring-1 focus:ring-dourado focus:outline-none">
                        @foreach ($meses as $indice => $nomeMes)
                            <option value="{{ $indice + 1 }}" @selected((int) old('mes', $feriadoEmEdicao?->mes) === $indice + 1)>{{ ucfirst($nomeMes) }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label for="feriado_ano" class="text-xs font-medium text-texto/60">Ano (só se não repetir)</label>
                    <input type="number" id="feriado_ano" name="ano" min="2020"
                           value="{{ old('ano', $feriadoEmEdicao?->ano) }}"
                           class="mt-1 block w-24 rounded-lg border border-bege bg-offwhite px-3 py-2 text-sm text-texto focus:border-dourado focus:ring-1 focus:ring-dourado focus:outline-none">
                </div>
                <label class="flex items-center gap-2 text-sm text-texto/70">
                    <input type="hidden" name="recorrente_anual" value="0">
                    <input type="checkbox" name="recorrente_anual" value="1"
                           @checked(old('recorrente_anual', $feriadoEmEdicao?->recorrente_anual ?? true))
                           class="h-4 w-4 rounded border-bege text-verde focus:ring-dourado">
                    Repete todo ano
                </label>
                <button type="submit" class="rounded-full bg-verde px-4 py-2 text-sm font-semibold text-white transition-colors hover:bg-verde-escuro">
                    {{ $feriadoEmEdicao ? 'Atualizar' : 'Adicionar' }}
                </button>
            </div>

            @if ($conflito && $conflito['tipo'] === 'feriado')
                <x-admin.disponibilidade.painel-conflito :afetadas="$conflito['afetadas']" />
            @endif
        </form>
    </section>

    {{-- =====================================================
         4. DIAS BLOQUEADOS
    ====================================================== --}}
    <section class="mt-6 rounded-2xl border border-bege bg-white p-5 sm:p-6">
        <h2 class="font-serif text-lg font-semibold text-texto">Dias bloqueados</h2>
        <p class="mt-1 text-sm text-texto/60">Marque um dia inteiro como "sem atendimento" (ex.: viagem, compromisso pessoal).</p>

        @if ($bloqueios->isEmpty())
            <p class="mt-4 text-sm text-texto/50">Nenhum dia bloqueado.</p>
        @else
            <ul class="mt-4 divide-y divide-bege">
                @foreach ($bloqueios as $bloqueio)
                    <li class="flex flex-wrap items-center justify-between gap-3 py-3">
                        <div class="text-sm text-texto">
                            <span class="font-semibold">{{ $bloqueio->data->format('d/m/Y') }}</span>
                            — Sem atendimento
                            @if ($bloqueio->motivo)
                                <span class="text-texto/60">({{ $bloqueio->motivo }})</span>
                            @endif
                        </div>
                        <form method="POST" action="{{ route('admin.disponibilidade.excecoes.remover', $bloqueio) }}" x-data="{ confirmando: false }">
                            @csrf
                            @method('DELETE')
                            <button type="button" x-show="!confirmando" @click="confirmando = true" class="text-sm font-medium text-red-700 underline underline-offset-2">Remover</button>
                            <span x-show="confirmando" x-cloak class="inline-flex items-center gap-2 text-sm">
                                Remover este bloqueio?
                                <button type="submit" class="font-semibold text-red-700 underline">Sim</button>
                                <button type="button" @click="confirmando = false" class="text-texto/60 underline">Não</button>
                            </span>
                        </form>
                    </li>
                @endforeach
            </ul>
        @endif

        <form id="form-bloqueio" method="POST" action="{{ route('admin.disponibilidade.excecoes.salvar') }}" class="mt-5 border-t border-bege pt-5">
            @csrf
            <input type="hidden" name="tipo" value="fechado_total">
            @if ($bloqueioEmEdicao)
                <input type="hidden" name="id" value="{{ $bloqueioEmEdicao->id }}">
            @endif

            <div class="flex flex-wrap items-end gap-3">
                <div>
                    <label for="bloqueio_data" class="text-xs font-medium text-texto/60">Data</label>
                    <input type="date" id="bloqueio_data" name="data"
                           value="{{ old('data', $bloqueioEmEdicao?->data?->toDateString()) }}"
                           class="mt-1 block rounded-lg border border-bege bg-offwhite px-3 py-2 text-sm text-texto focus:border-dourado focus:ring-1 focus:ring-dourado focus:outline-none">
                </div>
                <div class="flex-1">
                    <label for="bloqueio_motivo" class="text-xs font-medium text-texto/60">Motivo (opcional)</label>
                    <input type="text" id="bloqueio_motivo" name="motivo" maxlength="255"
                           value="{{ old('motivo', $bloqueioEmEdicao?->motivo) }}"
                           class="mt-1 block w-full rounded-lg border border-bege bg-offwhite px-3 py-2 text-sm text-texto focus:border-dourado focus:ring-1 focus:ring-dourado focus:outline-none">
                </div>
                <button type="submit" class="rounded-full bg-verde px-4 py-2 text-sm font-semibold text-white transition-colors hover:bg-verde-escuro">
                    {{ $bloqueioEmEdicao ? 'Atualizar' : 'Bloquear dia' }}
                </button>
            </div>

            @if ($conflito && $conflito['tipo'] === 'bloqueio')
                <x-admin.disponibilidade.painel-conflito :afetadas="$conflito['afetadas']" />
            @endif
        </form>
    </section>
</x-admin-layout>
