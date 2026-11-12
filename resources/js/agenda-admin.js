const MENSAGEM_ERRO_DETALHE = 'Não foi possível carregar os dados desta consulta. Verifique sua conexão e tente novamente.';
const TIMEOUT_MS = 12000;

const TEXTOS_CONFIRMACAO = {
    cancelar: 'Tem certeza que deseja cancelar esta consulta? Esta ação não pode ser desfeita.',
    concluir: 'Confirmar que esta consulta foi realizada e marcá-la como concluída?',
    'nao-compareceu': 'Confirmar que o cliente não compareceu a esta consulta?',
};

/**
 * Busca JSON tratando explicitamente os casos de falha — mesmo contrato do
 * buscarDisponibilidade() em resources/js/agendamento.js.
 *
 * @return {Promise<{ok: true, dados: any} | {ok: false, mensagem: string}>}
 */
async function buscarJson(url) {
    const controller = new AbortController();
    const timeoutId = setTimeout(() => controller.abort(), TIMEOUT_MS);

    try {
        const resposta = await fetch(url, {
            headers: { Accept: 'application/json' },
            signal: controller.signal,
        });

        if (!resposta.ok) {
            return { ok: false, mensagem: MENSAGEM_ERRO_DETALHE };
        }

        try {
            const dados = await resposta.json();

            return { ok: true, dados };
        } catch {
            return { ok: false, mensagem: MENSAGEM_ERRO_DETALHE };
        }
    } catch {
        return { ok: false, mensagem: MENSAGEM_ERRO_DETALHE };
    } finally {
        clearTimeout(timeoutId);
    }
}

/**
 * Painel da agenda administrativa. Só cuida do modal de detalhe —
 * a listagem, filtros e navegação por data são links/forms GET normais,
 * server-rendered, sem JS necessário.
 */
export default function agendaAdministrativa() {
    return {
        modalAberto: false,
        carregando: false,
        erro: null,
        detalhe: null,
        confirmando: null,
        motivoCancelamento: '',
        enviando: false,
        _ultimoIdAberto: null,
        _elementoAcionador: null,

        async abrirDetalhe(id) {
            // Guarda o elemento que tinha foco (o cartão clicado) para
            // devolver o foco a ele quando o modal fechar.
            this._elementoAcionador = document.activeElement;

            this._ultimoIdAberto = id;
            this.modalAberto = true;
            this.carregando = true;
            this.erro = null;
            this.detalhe = null;
            this.confirmando = null;
            this.motivoCancelamento = '';

            // Move o foco para dentro do modal assim que ele existir no DOM
            // (o botão Fechar é o único elemento sempre presente, em
            // qualquer estado — carregando, erro ou detalhe carregado).
            this.$nextTick(() => this.$refs.botaoFechar?.focus());

            const resultado = await buscarJson(`/admin/agenda/${id}`);

            if (resultado.ok) {
                this.detalhe = resultado.dados;
            } else {
                this.erro = resultado.mensagem;
            }

            this.carregando = false;
        },

        tentarNovamente() {
            if (this._ultimoIdAberto) {
                this.abrirDetalhe(this._ultimoIdAberto);
            }
        },

        fecharModal() {
            this.modalAberto = false;
            this.confirmando = null;
            this._elementoAcionador?.focus?.();
            this._elementoAcionador = null;
        },

        /**
         * Mantém o foco dentro do modal enquanto ele estiver aberto (Tab no
         * último elemento focável volta ao primeiro, Shift+Tab no primeiro
         * vai ao último). Recalculado a cada Tab porque o conjunto de elementos
         * focáveis muda conforme o estado (carregando/erro/detalhe/confirmando).
         */
        aprisionarFoco(event) {
            const raiz = event.currentTarget;
            const focaveis = Array.from(
                raiz.querySelectorAll('button:not([disabled]), a[href], input:not([disabled]), textarea:not([disabled]), select:not([disabled]), [tabindex]:not([tabindex="-1"])')
            ).filter((el) => el.offsetParent !== null);

            if (focaveis.length === 0) {
                return;
            }

            const primeiro = focaveis[0];
            const ultimo = focaveis[focaveis.length - 1];

            if (event.shiftKey && document.activeElement === primeiro) {
                event.preventDefault();
                ultimo.focus();
            } else if (!event.shiftKey && document.activeElement === ultimo) {
                event.preventDefault();
                primeiro.focus();
            }
        },

        pedirConfirmacao(acao) {
            this.confirmando = acao;
        },

        textoConfirmacao() {
            return TEXTOS_CONFIRMACAO[this.confirmando] ?? '';
        },
    };
}
