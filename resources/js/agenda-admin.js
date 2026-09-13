const MENSAGEM_ERRO_DETALHE = 'Não foi possível carregar os dados desta consulta. Verifique sua conexão e tente novamente.';
const TIMEOUT_MS = 12000;

const TEXTOS_CONFIRMACAO = {
    cancelar: 'Tem certeza que deseja cancelar esta consulta? Esta ação não pode ser desfeita.',
    concluir: 'Confirmar que esta consulta foi realizada e marcá-la como concluída?',
    'nao-compareceu': 'Confirmar que o cliente não compareceu a esta consulta?',
};

/**
 * Busca JSON tratando explicitamente os casos de falha — mesmo contrato do
 * buscarDisponibilidade() em resources/js/agendamento.js (duplicado aqui de
 * propósito: alterar o fluxo público não é necessário para a Fase 4B).
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
 * Painel da agenda administrativa (Fase 4B). Só cuida do modal de detalhe —
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

        async abrirDetalhe(id) {
            this._ultimoIdAberto = id;
            this.modalAberto = true;
            this.carregando = true;
            this.erro = null;
            this.detalhe = null;
            this.confirmando = null;
            this.motivoCancelamento = '';

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
        },

        pedirConfirmacao(acao) {
            this.confirmando = acao;
        },

        textoConfirmacao() {
            return TEXTOS_CONFIRMACAO[this.confirmando] ?? '';
        },
    };
}
