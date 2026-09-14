@php
    $whatsapp = 'https://api.whatsapp.com/send/?phone=5511994227774&text=Ol%C3%A1%20Dra.%20Valdizia%2C%20vim%20pelo%20seu%20site%20e%20gostaria%20de%20tirar%20algumas%20d%C3%BAvidas.&type=phone_number&app_absent=0';
    $rotulosPasso = ['Área', 'Data', 'Horário', 'Dados', 'Confirmação'];
@endphp

<x-layout title="Agende sua consulta — Dra. Valdizia Melo">
    <section
        x-data="agendamentoWizard({
            areas: {{ Js::from($areas->map(fn ($a) => ['id' => $a->id, 'nome' => $a->nome, 'icone' => $a->icone, 'descricao' => $a->descricao])) }},
            hoje: {{ Js::from(now()->toDateString()) }},
            old: {{ Js::from(old()) }},
            erros: {{ Js::from($errors->toArray()) }},
        })"
        x-cloak
        class="bg-offwhite px-6 py-16 lg:px-10 lg:py-20"
    >
        <div class="mx-auto max-w-3xl">
            <p class="eyebrow text-center text-dourado-texto">Agendamento</p>
            <h1 class="mt-3 text-center font-serif text-3xl font-semibold text-texto sm:text-4xl">
                Agende sua consulta inicial
            </h1>
            <p class="mx-auto mt-3 max-w-md text-center text-sm text-texto/70">
                Em poucos passos, escolha a área, a data e o horário que funcionam melhor para você.
            </p>

            <p class="sr-only" role="status" aria-live="polite"
               x-text="'Etapa ' + step + ' de 5: ' + {{ Js::from($rotulosPasso) }}[step - 1]"></p>

            {{-- Indicador de progresso --}}
            <ol class="mt-10 flex items-center justify-between" aria-label="Etapas do agendamento">
                @foreach ($rotulosPasso as $indice => $rotulo)
                    @php($numero = $indice + 1)
                    <li class="flex flex-1 flex-col items-center gap-2 {{ $indice > 0 ? 'relative before:absolute before:right-1/2 before:top-4 before:h-px before:w-full before:bg-bege' : '' }}">
                        <button
                            type="button"
                            @click="irParaStep({{ $numero }})"
                            :disabled="{{ $numero }} > maxStepAlcancado"
                            :aria-current="step === {{ $numero }} ? 'step' : null"
                            :aria-label="'{{ $rotulo }}' + (step === {{ $numero }} ? ' (etapa atual)' : '')"
                            class="relative z-10 flex h-8 w-8 shrink-0 items-center justify-center rounded-full border text-sm font-semibold transition-colors sm:h-9 sm:w-9"
                            :class="step === {{ $numero }}
                                ? 'border-verde bg-verde text-white'
                                : ({{ $numero }} <= maxStepAlcancado ? 'border-dourado-texto bg-offwhite text-dourado-texto' : 'border-bege bg-offwhite text-texto/30 cursor-not-allowed')"
                        >{{ $numero }}</button>
                        <span class="hidden text-[0.65rem] font-medium tracking-wide text-texto/60 uppercase sm:block">{{ $rotulo }}</span>
                    </li>
                @endforeach
            </ol>

            {{-- Erros de validação gerais (ex.: consentimento não marcado) --}}
            @if ($errors->any())
                <div class="mt-6 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800" role="alert">
                    <ul class="list-disc space-y-1 pl-4">
                        @foreach ($errors->all() as $mensagemErro)
                            <li>{{ $mensagemErro }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <form x-ref="form" method="POST" action="{{ route('agendamento.store') }}" @submit.prevent="onSubmit" class="mt-8" novalidate>
                @csrf
                <input type="hidden" name="area_atuacao_id" :value="areaId">
                <input type="hidden" name="data" :value="dataSelecionada">
                <input type="hidden" name="hora_inicio" :value="horaSelecionada">

                {{-- PASSO 1 — Área de atuação --}}
                <div x-show="step === 1" x-cloak x-transition:enter="transition ease-out duration-150" x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100">
                    <h2 class="sr-only">Escolha a área de atuação</h2>
                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
                        @foreach ($areas as $area)
                            <button
                                type="button"
                                @click="escolherArea({{ $area->id }})"
                                :aria-pressed="areaId === {{ $area->id }}"
                                class="flex flex-col items-center gap-3 rounded-2xl border bg-white p-6 text-center transition-colors"
                                :class="areaId === {{ $area->id }} ? 'border-verde ring-1 ring-verde' : 'border-bege hover:border-dourado/50'"
                            >
                                <span class="flex h-12 w-12 items-center justify-center rounded-xl bg-verde text-white">
                                    <x-ui.icon :name="$area->icone" class="h-5 w-5" />
                                </span>
                                <span class="font-serif text-base font-semibold text-texto">{{ $area->nome }}</span>
                            </button>
                        @endforeach
                    </div>
                </div>

                {{-- PASSO 2 — Data --}}
                <div x-show="step === 2" x-cloak x-transition:enter="transition ease-out duration-150" x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100">
                    <h2 class="sr-only">Escolha a data</h2>
                    <div class="rounded-2xl border border-bege bg-white p-5 sm:p-8">
                        <div class="flex items-center justify-between">
                            <button type="button" @click="mesAnterior" :disabled="!podeVoltarMes"
                                    aria-label="Mês anterior"
                                    class="flex h-9 w-9 items-center justify-center rounded-full border border-bege text-texto transition-colors hover:border-dourado disabled:cursor-not-allowed disabled:opacity-30">
                                <x-ui.icon name="arrow-right" class="h-4 w-4 rotate-180" />
                            </button>
                            <p class="font-serif text-lg font-semibold text-texto capitalize" x-text="mesAtualFormatado"></p>
                            <button type="button" @click="proximoMes" aria-label="Próximo mês"
                                    class="flex h-9 w-9 items-center justify-center rounded-full border border-bege text-texto transition-colors hover:border-dourado">
                                <x-ui.icon name="arrow-right" class="h-4 w-4" />
                            </button>
                        </div>

                        <p x-show="carregandoMes" x-cloak class="mt-6 text-center text-sm text-texto/60">Carregando disponibilidade…</p>

                        {{-- Erro técnico ao carregar o mês — nunca deve parecer "sem vagas". --}}
                        <div x-show="!carregandoMes && erroMes" x-cloak class="mt-6 rounded-lg border border-red-200 bg-red-50 px-4 py-4 text-center" role="alert">
                            <p class="text-sm text-red-800" x-text="erroMes"></p>
                            <button type="button" @click="carregarMes" class="btn-verde mt-3">
                                Tentar novamente
                            </button>
                        </div>

                        <div x-show="!carregandoMes && !erroMes" x-cloak>
                            <div class="mt-6 grid grid-cols-7 gap-1 text-center text-xs font-semibold tracking-wide text-texto/50 uppercase sm:gap-2">
                                <template x-for="d in diasSemana" :key="d">
                                    <span x-text="d"></span>
                                </template>
                            </div>

                            <div class="relative mt-2 grid grid-cols-7 gap-1 sm:gap-2" role="grid" aria-label="Calendário de datas disponíveis">
                                <template x-for="(dia, indice) in diasDoMes" :key="indice">
                                    <div class="flex items-center justify-center">
                                        <button
                                            type="button"
                                            x-show="dia"
                                            @click="dia && escolherData(dia)"
                                            :disabled="!dia || dia.passada || !dia.disponivel"
                                            :aria-label="dia ? (dia.dia + (dia.disponivel && !dia.passada ? ' — disponível' : ' — indisponível')) : ''"
                                            :aria-pressed="dia && dataSelecionada === dia.data"
                                            class="flex h-9 w-9 items-center justify-center rounded-full border text-sm font-medium transition-colors sm:h-11 sm:w-11"
                                            :class="{
                                                'border-verde bg-verde text-white': dia && dataSelecionada === dia.data,
                                                'border-dourado/40 text-texto hover:border-dourado hover:bg-bege/30 cursor-pointer': dia && !dia.passada && dia.disponivel && dataSelecionada !== dia.data,
                                                'border-transparent text-texto/25 cursor-not-allowed': dia && (dia.passada || !dia.disponivel),
                                                'ring-1 ring-dourado': dia && dia.hoje && dataSelecionada !== dia.data,
                                            }"
                                        ><span x-text="dia ? dia.dia : ''"></span></button>
                                    </div>
                                </template>
                            </div>

                            <div class="mt-6 flex items-center gap-4 text-xs text-texto/60">
                                <span class="flex items-center gap-1.5"><span class="h-2.5 w-2.5 rounded-full border border-dourado/40"></span> Disponível</span>
                                <span class="flex items-center gap-1.5"><span class="h-2.5 w-2.5 rounded-full bg-bege"></span> Indisponível</span>
                            </div>
                        </div>
                    </div>

                    <button type="button" @click="irParaStep(1)" class="link-arrow mt-6 text-dourado-texto">
                        <x-ui.icon name="arrow-right" class="h-4 w-4 rotate-180" />
                        Voltar e trocar a área de atuação
                    </button>
                </div>

                {{-- PASSO 3 — Horário --}}
                <div x-show="step === 3" x-cloak x-transition:enter="transition ease-out duration-150" x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100">
                    <h2 class="sr-only">Escolha o horário</h2>
                    <div class="rounded-2xl border border-bege bg-white p-5 sm:p-8">
                        <p class="font-serif text-lg font-semibold text-texto" x-text="dataSelecionadaFormatada"></p>

                        <p x-show="carregandoHorarios" x-cloak class="mt-4 text-sm text-texto/60">Carregando horários…</p>

                        {{-- Erro técnico ao carregar horários — nunca deve parecer "sem horários". --}}
                        <div x-show="!carregandoHorarios && erroHorarios" x-cloak class="mt-5 rounded-lg border border-red-200 bg-red-50 px-4 py-4 text-center" role="alert">
                            <p class="text-sm text-red-800" x-text="erroHorarios"></p>
                            <button type="button" @click="carregarHorarios" class="btn-verde mt-3">
                                Tentar novamente
                            </button>
                        </div>

                        <div x-show="!carregandoHorarios && !erroHorarios && horarios.length > 0" x-cloak class="mt-5 grid grid-cols-3 gap-3 sm:grid-cols-4">
                            <template x-for="horario in horarios" :key="horario.inicio">
                                <button
                                    type="button"
                                    @click="escolherHorario(horario)"
                                    :aria-pressed="horaSelecionada === horario.inicio"
                                    class="rounded-lg border py-2.5 text-sm font-medium transition-colors"
                                    :class="horaSelecionada === horario.inicio ? 'border-verde bg-verde text-white' : 'border-dourado/40 text-texto hover:border-dourado hover:bg-bege/30'"
                                    x-text="horario.inicio"
                                ></button>
                            </template>
                        </div>

                        <div x-show="!carregandoHorarios && buscouHorarios && horarios.length === 0" x-cloak class="mt-5">
                            <p class="text-sm text-texto/70">Não há horários disponíveis nesta data.</p>
                            <button type="button" @click="irParaStep(2)" class="link-arrow mt-3 text-dourado-texto">
                                Escolher outra data
                                <x-ui.icon name="arrow-right" class="h-4 w-4" />
                            </button>
                        </div>

                        @error('hora_inicio')
                            <p class="mt-4 text-sm font-medium text-red-700" role="alert">{{ $message }}</p>
                        @enderror
                    </div>

                    <button type="button" @click="irParaStep(2)" class="link-arrow mt-6 text-dourado-texto">
                        <x-ui.icon name="arrow-right" class="h-4 w-4 rotate-180" />
                        Voltar e escolher outra data
                    </button>
                </div>

                {{-- PASSO 4 — Dados do cliente --}}
                <div x-show="step === 4" x-cloak x-transition:enter="transition ease-out duration-150" x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100">
                    <h2 class="sr-only">Seus dados</h2>
                    <div class="space-y-5 rounded-2xl border border-bege bg-white p-5 sm:p-8">
                        <div>
                            <label for="nome_cliente" class="text-sm font-medium text-texto">Nome completo</label>
                            <input type="text" id="nome_cliente" name="nome_cliente" x-model="form.nome_cliente" autocomplete="name"
                                   aria-required="true" @if ($errors->has('nome_cliente')) aria-invalid="true" aria-describedby="erro-nome" @endif
                                   class="mt-1.5 w-full rounded-lg border border-bege bg-offwhite px-4 py-2.5 text-sm text-texto focus:border-dourado focus:ring-1 focus:ring-dourado focus:outline-none">
                            @error('nome_cliente')
                                <p id="erro-nome" class="mt-1.5 text-xs font-medium text-red-700" role="alert">{{ $message }}</p>
                            @enderror
                        </div>

                        <div class="grid grid-cols-1 gap-5 sm:grid-cols-2">
                            <div>
                                <label for="email_cliente" class="text-sm font-medium text-texto">E-mail</label>
                                <input type="email" id="email_cliente" name="email_cliente" x-model="form.email_cliente" autocomplete="email"
                                       aria-required="true" @if ($errors->has('email_cliente')) aria-invalid="true" aria-describedby="erro-email" @endif
                                       class="mt-1.5 w-full rounded-lg border border-bege bg-offwhite px-4 py-2.5 text-sm text-texto focus:border-dourado focus:ring-1 focus:ring-dourado focus:outline-none">
                                @error('email_cliente')
                                    <p id="erro-email" class="mt-1.5 text-xs font-medium text-red-700" role="alert">{{ $message }}</p>
                                @enderror
                            </div>
                            <div>
                                <label for="telefone_cliente" class="text-sm font-medium text-texto">Telefone</label>
                                <input type="tel" id="telefone_cliente" name="telefone_cliente" x-model="form.telefone_cliente" autocomplete="tel"
                                       aria-required="true" @if ($errors->has('telefone_cliente')) aria-invalid="true" aria-describedby="erro-telefone" @endif
                                       class="mt-1.5 w-full rounded-lg border border-bege bg-offwhite px-4 py-2.5 text-sm text-texto focus:border-dourado focus:ring-1 focus:ring-dourado focus:outline-none">
                                @error('telefone_cliente')
                                    <p id="erro-telefone" class="mt-1.5 text-xs font-medium text-red-700" role="alert">{{ $message }}</p>
                                @enderror
                            </div>
                        </div>

                        <div>
                            <label for="assunto" class="text-sm font-medium text-texto">Assunto da consulta</label>
                            <input type="text" id="assunto" name="assunto" x-model="form.assunto" maxlength="150"
                                   aria-required="true" @if ($errors->has('assunto')) aria-invalid="true" aria-describedby="erro-assunto" @endif
                                   placeholder="Ex.: Aposentadoria por idade, Pensão por morte, Divórcio…"
                                   class="mt-1.5 w-full rounded-lg border border-bege bg-offwhite px-4 py-2.5 text-sm text-texto focus:border-dourado focus:ring-1 focus:ring-dourado focus:outline-none">
                            @error('assunto')
                                <p id="erro-assunto" class="mt-1.5 text-xs font-medium text-red-700" role="alert">{{ $message }}</p>
                            @enderror
                        </div>

                        <div>
                            <label for="observacao" class="text-sm font-medium text-texto">Observação / motivo do contato <span class="font-normal text-texto/50">(opcional)</span></label>
                            <textarea id="observacao" name="observacao" x-model="form.observacao" rows="3"
                                      class="mt-1.5 w-full rounded-lg border border-bege bg-offwhite px-4 py-2.5 text-sm text-texto focus:border-dourado focus:ring-1 focus:ring-dourado focus:outline-none"></textarea>
                        </div>

                        <div class="flex items-start gap-3 border-t border-bege pt-5">
                            <input type="checkbox" id="consentimento" name="consentimento" value="1" x-model="consentimento"
                                   class="mt-0.5 h-4 w-4 rounded border-bege text-verde focus:ring-dourado">
                            <label for="consentimento" class="text-sm text-texto/80">
                                Li e concordo com a
                                <a href="{{ route('politica-privacidade') }}" target="_blank" class="font-medium text-dourado-texto underline underline-offset-2">Política de Privacidade</a>.
                                Meus dados serão usados apenas para viabilizar este atendimento.
                            </label>
                        </div>
                    </div>

                    <div class="mt-6 flex items-center justify-between">
                        <button type="button" @click="irParaStep(3)" class="link-arrow text-dourado-texto">
                            <x-ui.icon name="arrow-right" class="h-4 w-4 rotate-180" />
                            Voltar
                        </button>
                        <button type="button" @click="irParaConfirmacao" :disabled="!podeAvancarParaConfirmacao()"
                                class="btn-verde disabled:cursor-not-allowed disabled:opacity-40">
                            Revisar e confirmar
                            <x-ui.icon name="arrow-right" class="h-4 w-4" />
                        </button>
                    </div>
                </div>

                {{-- PASSO 5 — Resumo e confirmação --}}
                <div x-show="step === 5" x-cloak x-transition:enter="transition ease-out duration-150" x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100">
                    <h2 class="sr-only">Resumo e confirmação</h2>
                    <dl class="grid grid-cols-1 gap-x-6 gap-y-4 rounded-2xl border border-bege bg-white p-5 sm:grid-cols-2 sm:p-8">
                        <div>
                            <dt class="text-xs font-semibold tracking-wide text-texto/50 uppercase">Área</dt>
                            <dd class="mt-1 font-serif text-base text-texto" x-text="areaSelecionada ? areaSelecionada.nome : ''"></dd>
                        </div>
                        <div>
                            <dt class="text-xs font-semibold tracking-wide text-texto/50 uppercase">Data</dt>
                            <dd class="mt-1 font-serif text-base text-texto capitalize" x-text="dataSelecionadaFormatada"></dd>
                        </div>
                        <div>
                            <dt class="text-xs font-semibold tracking-wide text-texto/50 uppercase">Horário</dt>
                            <dd class="mt-1 font-serif text-base text-texto" x-text="horaSelecionada"></dd>
                        </div>
                        <div>
                            <dt class="text-xs font-semibold tracking-wide text-texto/50 uppercase">Nome</dt>
                            <dd class="mt-1 text-base text-texto" x-text="form.nome_cliente"></dd>
                        </div>
                        <div>
                            <dt class="text-xs font-semibold tracking-wide text-texto/50 uppercase">E-mail</dt>
                            <dd class="mt-1 text-base text-texto" x-text="form.email_cliente"></dd>
                        </div>
                        <div>
                            <dt class="text-xs font-semibold tracking-wide text-texto/50 uppercase">Telefone</dt>
                            <dd class="mt-1 text-base text-texto" x-text="form.telefone_cliente"></dd>
                        </div>
                        <div class="sm:col-span-2">
                            <dt class="text-xs font-semibold tracking-wide text-texto/50 uppercase">Assunto</dt>
                            <dd class="mt-1 text-base text-texto" x-text="form.assunto"></dd>
                        </div>
                    </dl>

                    <div class="mt-6 flex items-center justify-between">
                        <button type="button" @click="irParaStep(4)" class="link-arrow text-dourado-texto">
                            <x-ui.icon name="arrow-right" class="h-4 w-4 rotate-180" />
                            Voltar e editar
                        </button>
                        <button type="submit" :disabled="enviando" class="btn-gold disabled:cursor-not-allowed disabled:opacity-60">
                            <span x-show="!enviando">Confirmar agendamento</span>
                            <span x-show="enviando" x-cloak>Enviando…</span>
                        </button>
                    </div>
                </div>
            </form>

            <p class="mt-12 text-center text-sm text-texto/60">
                Prefere falar diretamente?
                <a href="{{ $whatsapp }}" target="_blank" rel="noopener" class="link-arrow inline-flex text-dourado-texto">
                    Fale pelo WhatsApp
                    <x-ui.icon name="whatsapp" class="h-4 w-4" />
                </a>
            </p>
        </div>
    </section>
</x-layout>
