const DIAS_SEMANA = ['Dom', 'Seg', 'Ter', 'Qua', 'Qui', 'Sex', 'Sáb'];
const MESES = [
    'janeiro', 'fevereiro', 'março', 'abril', 'maio', 'junho',
    'julho', 'agosto', 'setembro', 'outubro', 'novembro', 'dezembro',
];

function paraMesISO(data) {
    return `${data.getFullYear()}-${String(data.getMonth() + 1).padStart(2, '0')}`;
}

const MENSAGEM_ERRO_DISPONIBILIDADE = 'Não foi possível carregar a disponibilidade. Verifique sua conexão e tente novamente.';

const TIMEOUT_MS = 12000;

/**
 * Busca JSON tratando explicitamente os casos de falha:
 * resposta HTTP não-2xx (inclui 429 do rate limit), erro de rede, timeout de
 * conexão lenta e corpo que não é JSON válido. Nunca deixa um erro técnico
 * silenciosamente virar "não há horários" — sempre retorna um estado
 * explícito de sucesso ou falha para a UI decidir o que mostrar.
 *
 * @return {Promise<{ok: true, dados: any} | {ok: false, mensagem: string}>}
 */
async function buscarDisponibilidade(url) {
    const controller = new AbortController();
    const timeoutId = setTimeout(() => controller.abort(), TIMEOUT_MS);

    try {
        const resposta = await fetch(url, {
            headers: { Accept: 'application/json' },
            signal: controller.signal,
        });

        if (!resposta.ok) {
            // Inclui 429 (rate limit), 4xx e 5xx — nenhum deles significa
            // "sem horários", só que a requisição não foi bem-sucedida.
            return { ok: false, mensagem: MENSAGEM_ERRO_DISPONIBILIDADE };
        }

        try {
            const dados = await resposta.json();

            return { ok: true, dados };
        } catch {
            // Corpo de resposta que não é JSON válido.
            return { ok: false, mensagem: MENSAGEM_ERRO_DISPONIBILIDADE };
        }
    } catch (erro) {
        // Erro de rede (offline, DNS, CORS) ou timeout via AbortController.
        return { ok: false, mensagem: MENSAGEM_ERRO_DISPONIBILIDADE };
    } finally {
        clearTimeout(timeoutId);
    }
}

/**
 * Wizard de agendamento público. Toda a disponibilidade vem do
 * backend (AvailabilityService, via os endpoints /agendamento/disponibilidade
 * e /agendamento/horarios) — este arquivo só desenha a interface e nunca
 * decide sozinho o que está disponível.
 */
export default function agendamentoWizard(config) {
    return {
        // --- estado geral ---
        step: 1,
        maxStepAlcancado: 1,
        areas: config.areas,
        hoje: config.hoje,
        diasSemana: DIAS_SEMANA,

        // --- passo 1: área ---
        areaId: config.old.area_atuacao_id ? Number(config.old.area_atuacao_id) : null,

        // --- passo 2: data ---
        mesAtual: config.old.data ? config.old.data.slice(0, 7) : config.hoje.slice(0, 7),
        diasDoMes: [],
        carregandoMes: false,
        erroMes: null,
        dataSelecionada: config.old.data ?? null,

        // --- passo 3: horário ---
        horarios: [],
        carregandoHorarios: false,
        erroHorarios: null,
        horaSelecionada: config.old.hora_inicio ?? null,
        buscouHorarios: false,

        // --- passo 4: dados ---
        form: {
            nome_cliente: config.old.nome_cliente ?? '',
            email_cliente: config.old.email_cliente ?? '',
            telefone_cliente: config.old.telefone_cliente ?? '',
            assunto: config.old.assunto ?? '',
            observacao: config.old.observacao ?? '',
        },
        consentimento: false,

        // --- envio ---
        enviando: false,
        erros: config.erros ?? {},

        get areaSelecionada() {
            return this.areas.find((a) => a.id === this.areaId) ?? null;
        },

        get dataSelecionadaFormatada() {
            if (!this.dataSelecionada) return '';

            return this.formatarData(this.dataSelecionada);
        },

        get mesAtualFormatado() {
            const [ano, mes] = this.mesAtual.split('-').map(Number);

            return `${MESES[mes - 1]} de ${ano}`;
        },

        get podeVoltarMes() {
            return this.mesAtual > this.hoje.slice(0, 7);
        },

        formatarData(iso) {
            const [ano, mes, dia] = iso.split('-').map(Number);

            return `${dia} de ${MESES[mes - 1]} de ${ano}`;
        },

        init() {
            if (Object.keys(this.erros).length > 0) {
                // Reabre o wizard no ponto certo se o servidor recusou a
                // submissão (ex.: horário ocupado nesse meio-tempo, ou erro
                // de validação nos dados do cliente) — sem perder o que o
                // usuário já tinha preenchido.
                if (this.erros.hora_inicio && this.areaId && this.dataSelecionada) {
                    this.step = 3;
                    this.maxStepAlcancado = 3;
                    this.carregarMes();
                    this.carregarHorarios();
                } else if (this.areaId && this.dataSelecionada && this.horaSelecionada) {
                    this.step = 4;
                    this.maxStepAlcancado = 4;
                    this.carregarMes();
                }
            }
        },

        irParaStep(step) {
            if (step <= this.maxStepAlcancado) {
                this.step = step;
            }
        },

        escolherArea(id) {
            this.areaId = id;
            this.step = 2;
            this.maxStepAlcancado = Math.max(this.maxStepAlcancado, 2);
            this.dataSelecionada = null;
            this.horaSelecionada = null;
            this.carregarMes();
        },

        async carregarMes() {
            if (!this.areaId) return;

            this.carregandoMes = true;
            this.erroMes = null;

            const resultado = await buscarDisponibilidade(
                `/agendamento/disponibilidade?area_atuacao_id=${this.areaId}&mes=${this.mesAtual}`
            );

            if (resultado.ok) {
                this.montarGrade(resultado.dados.dias ?? {});
            } else {
                this.erroMes = resultado.mensagem;
                this.diasDoMes = [];
            }

            this.carregandoMes = false;
        },

        montarGrade(diasApi) {
            const [ano, mes] = this.mesAtual.split('-').map(Number);
            const primeiroDiaSemana = new Date(ano, mes - 1, 1).getDay();
            const totalDias = new Date(ano, mes, 0).getDate();

            const celulas = [];
            for (let i = 0; i < primeiroDiaSemana; i++) {
                celulas.push(null);
            }

            for (let dia = 1; dia <= totalDias; dia++) {
                const data = `${ano}-${String(mes).padStart(2, '0')}-${String(dia).padStart(2, '0')}`;
                celulas.push({
                    data,
                    dia,
                    passada: data < this.hoje,
                    hoje: data === this.hoje,
                    disponivel: Boolean(diasApi[data]),
                });
            }

            this.diasDoMes = celulas;
        },

        mesAnterior() {
            if (!this.podeVoltarMes) return;
            this.mudarMes(-1);
        },

        proximoMes() {
            this.mudarMes(1);
        },

        mudarMes(delta) {
            const [ano, mes] = this.mesAtual.split('-').map(Number);
            const data = new Date(ano, mes - 1 + delta, 1);
            this.mesAtual = paraMesISO(data);
            this.carregarMes();
        },

        async escolherData(dia) {
            if (!dia || dia.passada || !dia.disponivel) return;

            this.dataSelecionada = dia.data;
            this.horaSelecionada = null;
            this.step = 3;
            this.maxStepAlcancado = Math.max(this.maxStepAlcancado, 3);
            await this.carregarHorarios();
        },

        async carregarHorarios() {
            if (!this.areaId || !this.dataSelecionada) return;

            this.carregandoHorarios = true;
            this.erroHorarios = null;
            this.buscouHorarios = false;

            const resultado = await buscarDisponibilidade(
                `/agendamento/horarios?area_atuacao_id=${this.areaId}&data=${this.dataSelecionada}`
            );

            if (resultado.ok) {
                this.horarios = resultado.dados.horarios ?? [];
                this.buscouHorarios = true;
            } else {
                this.erroHorarios = resultado.mensagem;
                this.horarios = [];
                // "buscouHorarios" fica false de propósito: o estado "não há
                // horários nesta data" (vazio, mas sem erro) não deve ser
                // confundido com uma falha técnica de carregamento.
            }

            this.carregandoHorarios = false;
        },

        escolherHorario(horario) {
            this.horaSelecionada = horario.inicio;
            this.step = 4;
            this.maxStepAlcancado = Math.max(this.maxStepAlcancado, 4);
        },

        podeAvancarParaConfirmacao() {
            return (
                this.form.nome_cliente.trim() !== '' &&
                this.form.email_cliente.trim() !== '' &&
                this.form.telefone_cliente.trim() !== '' &&
                this.form.assunto.trim() !== '' &&
                this.consentimento
            );
        },

        irParaConfirmacao() {
            if (!this.podeAvancarParaConfirmacao()) return;

            this.step = 5;
            this.maxStepAlcancado = Math.max(this.maxStepAlcancado, 5);
        },

        onSubmit() {
            if (this.step !== 5 || this.enviando) return;

            this.enviando = true;
            this.$refs.form.submit();
        },
    };
}
