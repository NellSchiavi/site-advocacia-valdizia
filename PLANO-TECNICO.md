# Plano Técnico — Site Dra. Valdizia Melo (Advocacia e Assessoria Jurídica)

Documento de planejamento. Nenhum código foi implementado nesta fase.

---

## 0. Referência visual (spec fixa)

Extraído da referência visual fornecida:

- **Paleta**: `#0F2D27` (verde escuro — hero, faixa de CTA, ícones de área), `#F7F3EB` (fundo base), branco/quase-branco (`#FFFFFF`/`#FDFBF7`) nos cards com borda 1px `#E7DCCB`, dourado `#B08A55` (labels, links "Saiba mais", botão secundário, divisores), texto escuro `#14212B`.
- **Tipografia**: serif de alto contraste para títulos (peso editorial, ex. Playfair Display/Lora); sans-serif geométrica-humanista para nav/corpo/labels (ex. Inter/Work Sans); labels em caixa alta com letter-spacing largo e cor dourada.
- **Componentes visuais**: botões em pílula com ícone de calendário + seta; ícones de linha fina monocromáticos; blocos de ícone quadrado arredondado (verde escuro, ícone branco); cards com borda fina, sem sombra pesada; fotografia editorial real (não banco de imagens genérico), estatueta da balança usada como detalhe de cena, nunca como ícone principal.
- **Ordem estrutural**: Header · Hero (texto + foto) · Diferenciais inline (4 itens) · Áreas de Atuação (heading + 3 cards) · Sobre (foto + bio + lista de valores) · Faixa de CTA de agendamento (fundo escuro) · Footer.
- **Regra**: esta composição é requisito, não sugestão. Não introduzir cards extras, ícones jurídicos clichês (martelo, balança gigante), gradientes ou excesso de dourado.

---

## 1. Arquitetura recomendada

**Monolito server-rendered** (não SPA, não headless separado):

- Backend Laravel 11 (PHP 8.3) servindo tanto as páginas públicas (Blade + Tailwind CSS + Alpine.js para interatividade pontual) quanto a API interna de agendamento e o painel administrativo, no mesmo deploy.
- Motivo: os requisitos centrais (agendamento real, admin autenticado, e-mail, segurança) são lógica de servidor com estado — não um site estático. Um monolito PHP+MySQL é o que roda nativamente em qualquer plano de hospedagem compartilhada da Locaweb, sem exigir VPS/Node.
- Frontend não usa React/Next: Blade components + Tailwind reproduzem pixel-a-pixel a referência sem overhead de SPA, e mantêm a stack 100% compatível com hospedagem compartilhada barata.

## 2. Stack recomendada

- **Linguagem/Framework**: PHP 8.3 + Laravel 11
- **Banco**: MySQL 8 (mesmo motor em dev e produção — evita divergência de tipos/collation)
- **Frontend**: Blade + Tailwind CSS (design tokens = paleta acima) + Alpine.js (calendário de disponibilidade, wizard de agendamento, interações do admin) — sem build de SPA pesado
- **Auth admin**: Laravel Fortify (ou Breeze) — hash bcrypt, sessões em DB, CSRF nativo
- **E-mail**: Laravel Mail via API transacional (Resend ou Brevo) — não SMTP da conta Gmail da cliente
- **Fila/agendador**: Laravel Scheduler + Queue (driver `database`, sem Redis para manter compatível com shared hosting)
- **Dev local**: Laravel Sail (Docker Compose) — PHP-FPM, MySQL 8, Mailpit (captura e-mails localmente sem enviar de verdade)
- **Versionamento**: GitHub

## 3. Estrutura de pastas (prevista)

```
Site-Valdizia/
├── app/
│   ├── Http/Controllers/
│   │   ├── Public/ (Home, Sobre, AreasController, AgendamentoController)
│   │   └── Admin/ (DashboardController, AgendaController, ConfiguracaoController, BloqueioController)
│   ├── Models/ (Agendamento, AreaAtuacao, HorarioSemana, Bloqueio, Feriado, ConfiguracaoGlobal, User)
│   ├── Services/ (DisponibilidadeService, AgendamentoService, NotificacaoService)
│   ├── Mail/ (AgendamentoClienteMail, AgendamentoAdvogadaMail)
│   └── Policies/
├── resources/
│   ├── views/
│   │   ├── components/ (layout, header, footer, hero, cta-band, card-area, etc. — 1 componente por bloco da referência)
│   │   ├── public/ (home, sobre, areas, agendamento)
│   │   └── admin/ (login, dashboard, agenda, configuracoes)
│   └── css/app.css (tokens Tailwind com a paleta)
├── routes/ (web.php, admin.php)
├── database/
│   ├── migrations/
│   └── seeders/ (DadosIniciaisSeeder — horários padrão seg-sex 09h-18h, sáb/dom bloqueados)
├── docker/ (Sail overrides se necessário)
├── tests/ (Feature: agendamento, auth admin, disponibilidade)
├── .env.example
└── PLANO-TECNICO.md
```

## 4. Estratégia de banco de dados

- Dev local: MySQL 8 via Docker (Sail), idêntico ao motor de produção — elimina risco de migração de schema/tipos.
- Produção: MySQL da Locaweb, conectado só por variáveis de ambiente (`DB_HOST`, `DB_PORT`, `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD`) — zero mudança de código entre ambientes.
- Migrations Laravel versionadas no Git — nunca editar schema manualmente em produção.
- Backup: depende do plano Locaweb contratado (ver seção 16); complementar com `mysqldump` agendado via cron para armazenamento externo.

## 5. Estratégia de autenticação

- Painel administrativo único (ou multiusuário, a confirmar) protegido por Laravel Fortify: login com e-mail/senha, hash bcrypt (custo 12), sessões armazenadas em DB, cookies `HttpOnly` + `Secure` + `SameSite=Lax`.
- Rate limiting no login (ex. 5 tentativas/minuto por IP+e-mail) via `RateLimiter` do Laravel.
- CSRF token automático em todos os formulários Blade.
- Middleware `auth` + `verified` em todas as rotas `/admin/*`.
- 2FA (TOTP) como item opcional a decidir com a cliente — Fortify já suporta nativamente se ativado.

## 6. Estratégia de agendamento

**Modelo de disponibilidade** (calculado em runtime, não pré-gerado):

1. `HorarioSemana`: intervalos de atendimento por dia da semana (permite múltiplos intervalos, ex. manhã/tarde) — seed inicial seg-sex 09:00-18:00, sáb/dom sem registro (= bloqueado).
2. `ConfiguracaoGlobal`: duração da consulta (min), intervalo entre consultas (min), antecedência mínima/máxima para agendar.
3. `Bloqueio` / `Feriado`: datas ou intervalos de hora específicos indisponíveis (feriados podem ser recorrência anual).
4. `Agendamento`: compromissos já confirmados.

`DisponibilidadeService::slotsDisponiveis(area, data)` monta os horários do dia a partir de (1), subtrai (3) e (4), aplica (2) — retorna só o que está realmente livre. Consultado via endpoint `GET /agendamento/horarios-disponiveis?data=&area=` (AJAX/Alpine).

**Concorrência**: reserva de horário dentro de transação DB + constraint única (`data`+`hora_inicio`) para impedir double-booking em requisições simultâneas.

**Fluxo do cliente**: área de atuação → data (calendário só com dias com vaga) → horário disponível → nome/e-mail/telefone/observação → confirmação → grava `Agendamento` (status `pendente`/`confirmado`) → dispara e-mails.

## 7. Estratégia de envio de e-mails

- Driver: API transacional (Resend ou Brevo — decisão pendente, ver seção 18), configurado só via `.env` (`MAIL_MAILER`, `MAIL_API_KEY` etc.), nunca senha da conta Gmail da cliente no código.
- Dois e-mails por agendamento: notificação interna para `adv.valdizia@gmail.com` (todos os dados) e confirmação para o cliente (dados do agendamento + instruções).
- Filas (`queue: database`) para não travar a resposta HTTP esperando o envio.
- Mailpit em dev local para inspecionar e-mails sem enviar de verdade.

## 8. Estratégia de deploy

- Repositório único no GitHub, branch `main` protegida.
- CI simples (GitHub Actions): rodar testes + `composer install --no-dev` + build de assets Tailwind a cada push.
- Deploy para Locaweb: depende do tipo de acesso do plano contratado (FTP/SFTP vs Git vs SSH) — ver decisões pendentes (seção 18) e riscos (seção 17).

## 9. Estratégia para desenvolvimento local

- Laravel Sail (Docker Compose): app PHP, MySQL 8, Mailpit.
- `.env` local nunca commitado; `.env.example` versionado com todas as chaves necessárias e valores dummy.
- Seeders replicam a configuração inicial real (horários seg-sex 09-18h, sáb/dom/feriados bloqueados) para desenvolver contra dados realistas.

## 10. Estratégia para homologação

- Ambiente público separado da produção final, mesma stack (PHP+MySQL), para a cliente validar antes do domínio oficial estar no ar.
- Opção recomendada: contratar desde já o plano definitivo da Locaweb (ou um plano de entrada) e publicar a homologação em subdomínio temporário (`homolog.` ou path), migrando depois para o domínio oficial — evita qualquer diferença de ambiente entre homolog e produção.
- Alternativa mais barata/rápida: hospedagem PHP+MySQL gratuita/temporária (ex. InfinityFree) só para review visual da cliente, sem dados reais — usar apenas se a Locaweb ainda não estiver contratada quando a fase de homologação chegar.

## 11. Estratégia para produção na Locaweb

- Confirmar previamente (ver seção 16): versão de PHP disponível (precisa ≥8.2 para Laravel 11), acesso SSH/Composer, suporte a cron jobs, método de deploy (Git/FTP), limites de banco MySQL.
- HTTPS via certificado SSL gratuito que a Locaweb oferece nos planos de hospedagem.
- Variáveis de ambiente de produção configuradas direto no painel Locaweb ou em `.env` fora do webroot público — nunca no repositório.

## 12. Modelo inicial do banco (visão lógica)

```
users               (id, name, email, password, role, timestamps)
areas_atuacao       (id, slug, nome, descricao, icone, ordem, ativo)
configuracoes_globais (id, duracao_consulta_min, intervalo_entre_consultas_min,
                        antecedencia_minima_horas, antecedencia_maxima_dias)
horarios_semana     (id, dia_semana[0-6], hora_inicio, hora_fim, ativo)
feriados            (id, data, nome, recorrente_anual, bloqueado)
bloqueios           (id, data, hora_inicio nullable, hora_fim nullable, motivo, criado_por)
agendamentos        (id, area_atuacao_id, nome_cliente, email_cliente, telefone_cliente,
                     data, hora_inicio, hora_fim, observacao, status, created_at)
logs_admin          (id, user_id, acao, detalhes, ip, created_at)
```

## 13. Principais componentes da interface

Públicos (mapeados 1:1 com a referência): Header/Nav, Hero (texto+foto+citação), Faixa de 4 diferenciais, Heading + 3 Cards de Área de Atuação, Bloco Sobre (foto+bio+lista de valores), Faixa de CTA de agendamento, Footer, Formulário de Agendamento em etapas (área → data → horário → dados → confirmação), Calendário de disponibilidade.

Admin: Tela de login, Dashboard (resumo do dia/semana), Agenda/lista de agendamentos com ação de cancelar, Configuração de horários por dia da semana, Gestão de bloqueios/feriados, Configuração geral (duração/intervalo de consulta).

## 14. Rotas previstas

**Públicas**: `GET /`, `/sobre`, `/areas-de-atuacao`, `/areas-de-atuacao/{slug}`, `/contato`, `/agendamento`, `POST /agendamento`, `GET /agendamento/horarios-disponiveis`.

**Admin** (prefixo `/admin`, middleware `auth`): `/login`, `/dashboard`, `/agenda`, `/agendamentos/{id}/cancelar`, `/configuracoes/horarios`, `/configuracoes/bloqueios`, `/configuracoes/feriados`, `/configuracoes/geral`.

## 15. Medidas de segurança

Hash bcrypt de senha; sessões `HttpOnly`/`Secure`; CSRF em todos os forms; Eloquent ORM (parametrização nativa contra SQL injection); escapamento automático do Blade contra XSS; rate limiting em login e no endpoint de agendamento; middleware de autorização em todas as rotas `/admin`; HTTPS forçado; `.env` fora do webroot e fora do Git; log de ações administrativas (`logs_admin`); página de política de privacidade e checkbox de consentimento no formulário (LGPD); validação server-side de todos os inputs (Form Requests do Laravel).

## 16. Pontos que dependem da configuração da Locaweb

- Versão de PHP disponível no plano (precisa 8.2+).
- Acesso SSH e Composer (define método de deploy).
- Suporte e limite de cron jobs (necessário para o scheduler do Laravel — lembretes, limpeza de reservas expiradas).
- Se SMTP de saída é bloqueado/limitado (reforça a escolha por e-mail via API HTTP, não SMTP).
- Quantidade e tamanho de bancos MySQL permitidos.
- Política de backup automático inclusa no plano.

## 17. Riscos técnicos

1. Plano compartilhado sem SSH/Composer torna deploy manual (build local, upload via FTP/Git) — mais lento e propenso a erro.
2. Cron jobs limitados podem restringir a frequência/robustez do scheduler.
3. Bloqueio de SMTP de saída na hospedagem — mitigado usando provedor transacional via API.
4. Condição de corrida em agendamentos simultâneos no mesmo horário — mitigado com transação + constraint única.
5. Divergência de timezone entre servidor, banco e aplicação — fixar `America/Sao_Paulo` em todas as camadas.
6. Dependência do domínio/DNS da cliente para SSL e URLs finais em produção.

## 18. Decisões que precisam ser tomadas antes da implementação

1. **Plano Locaweb definitivo** — compartilhado (Hospedagem I/II) ou VPS/Cloud Server; e se dá SSH/Composer/cron. Isso finaliza o método de deploy.
2. **Provedor de e-mail transacional** — Resend, Brevo ou Mailgun (preciso de uma conta/API key).
3. **Domínio oficial** da cliente (ainda não informado) — necessário para SSL e para a fase de homologação.
4. **Estratégia de homologação**: contratar Locaweb desde já (subdomínio temporário) vs. host gratuito temporário só para review visual.
5. **Textos institucionais definitivos** (bio completa, política de privacidade, termos de uso) — hoje só há o essencial fornecido; o restante seria placeholder até a cliente aprovar.
6. **Admin único ou multiusuário** no painel.
7. Confirmar que Google Fonts (gratuitas, ex. Playfair Display + Inter) atendem à referência tipográfica, ou se a cliente tem fonte de marca licenciada específica.

---

**Status**: Fase 0 aprovada pela cliente/desenvolvedor. Segue abaixo a especificação técnica detalhada (Fase 0.5), ainda sem código.

---

# Especificação Técnica Detalhada — Fase 0.5

Aprofunda os pontos críticos antes da Fase 1 (implementação). Nenhum código, migration, componente ou dependência foi criado nesta etapa.

## 1. Design — princípio de fidelidade

A imagem de referência continua sendo a spec fixa de composição, hierarquia, paleta, proporções e sensação visual — não haverá redesenho.

Princípio adotado para a implementação: **fidelidade visual à referência, preservando composição, hierarquia, espaçamento e identidade visual, com adaptação responsiva, acessível e funcional para desktop, tablet e mobile.** Ou seja, o desktop deve corresponder à referência com a maior fidelidade possível; tablet e mobile herdam a mesma linguagem (cores, tipografia, iconografia, ordem das seções) reorganizando grid/colunas para telas estreitas (ex. hero texto+foto empilham verticalmente, os 4 diferenciais viram 2x2 ou lista vertical, os 3 cards de área empilham em coluna única) sem inventar elementos novos.

## 2. Agenda — mecanismo de disponibilidade

Pipeline de cálculo, aplicado nesta ordem para uma data e área solicitadas:

```
HORÁRIOS PADRÃO (availability_rules do dia da semana)
   → EXCEÇÕES (availability_exceptions da data específica — pode fechar, fechar parcialmente ou abrir horário especial)
      → FERIADOS (holidays cujo mês/dia batem com a data — se bloqueado=true, zera o dia inteiro)
         → BLOQUEIOS (mesma tabela availability_exceptions, tipo fechado_total/fechado_parcial — cobre bloqueios manuais pontuais)
            → AGENDAMENTOS (appointments ativos no dia — removem os slots já ocupados)
               → HORÁRIOS DISPONÍVEIS (resultado final devolvido ao formulário público)
```

Detalhe de cada camada:

1. **Horários padrão**: busca `availability_rules` com `day_of_week` = dia da semana da data e `ativo = true`. Pode haver múltiplos intervalos (ex. 09:00–12:00 e 14:00–18:00). Se não houver nenhuma regra ativa para aquele dia (ex. sábado/domingo por padrão), o dia já nasce sem nenhum slot.
2. **Exceções**: busca `availability_exceptions` com `data` = a data pesquisada.
   - `fechado_total` → zera todos os slots do dia, independente do passo 1.
   - `fechado_parcial` → remove o intervalo `hora_inicio`–`hora_fim` informado dos slots gerados no passo 1.
   - `horario_especial` → **substitui** por completo o horário padrão daquele dia pelo intervalo informado (permite, por exemplo, abrir um sábado específico ou mudar o expediente de uma terça-feira isolada).
3. **Feriados**: busca em `holidays` por `(mes, dia)` da data (e por `ano` quando o feriado não é recorrente). Se encontrado e `bloqueado = true`, zera o dia inteiro (mesmo efeito de um `fechado_total`, mas administrado em uma tela separada de "Feriados").
4. **Bloqueios manuais**: já cobertos no passo 2 (mesma tabela `availability_exceptions`) — a Dra. Valdizia usa a tela "Bloqueios" para criar registros `fechado_total`/`fechado_parcial` e a tela "Feriados" para `holidays`; o motor de disponibilidade trata os dois like camadas independentes, mas usam a mesma tabela por baixo no caso dos bloqueios pontuais.
5. **Agendamentos**: subtrai os slots que colidem com `appointments` cujo `status` esteja em `('pending','confirmed')` naquela data (agendamentos cancelados voltam a liberar o horário).
6. **Geração dos slots**: os intervalos resultantes dos passos 1–4 são fatiados em blocos de `duracao_consulta_min` (config global), com `intervalo_entre_consultas_min` de respiro entre um fim e o próximo início. Slots cujo início seja anterior a `antecedencia_minima_horas` a partir de agora, ou posterior a `antecedencia_maxima_dias`, são descartados.

Toda essa lógica vive em um único serviço (`DisponibilidadeService`), consumido tanto pelo endpoint público de horários quanto pela revalidação no momento da confirmação (seção 3) — **uma única fonte de verdade**, nunca duas implementações divergentes entre frontend e backend.

Capacidades administrativas mapeadas para telas:
- **Configurações → Horários da semana**: CRUD sobre `availability_rules` (alterar/adicionar/remover intervalos por dia).
- **Bloqueios**: CRUD sobre `availability_exceptions` (bloquear/desbloquear datas ou intervalos específicos).
- **Feriados**: CRUD sobre `holidays` (cadastrar/remover, marcar recorrência anual).
- **Configurações → Geral**: editar `duracao_consulta_min`, `intervalo_entre_consultas_min`, antecedência mínima/máxima.
- **Agenda/Agendamentos**: listagem e detalhe de `appointments`, ação de cancelar.

## 3. Concorrência de agendamento e prevenção de sobreposição

A disponibilidade mostrada no formulário é **sempre tratada como sugestão, nunca como garantia**. A solução final tem três camadas — a revisão desta fase corrige uma lacuna da versão anterior (ver "Por que `active_slot` + UNIQUE não é suficiente" abaixo).

**Camada 1 — revalidação no backend.** Ao receber `POST /agendamento`, o servidor roda de novo o `DisponibilidadeService` para a data/área/horário enviados. Se o slot não estiver mais entre os disponíveis (por ter sido reservado, bloqueado ou expirado nesse meio-tempo), a requisição é rejeitada com uma mensagem clara e a tela recarrega os horários atualizados — nunca confia no que o cliente enviou como "disponível".

**Por que `active_slot` + índice único não é suficiente sozinho:** o índice único sobre `(data, hora_inicio, active_slot)` só impede duas linhas com o **mesmo horário de início exato**. Ele não impede sobreposição de intervalos com horários de início diferentes. Exemplo concreto que esse índice **não pega**:

```
Consulta A: 14:00 → 15:00   (hora_inicio = 14:00)
Consulta B: 14:30 → 15:30   (hora_inicio = 14:30)
```

Como `hora_inicio` é diferente (14:00 ≠ 14:30), as duas linhas passam pelo índice único sem violação, mesmo sobrepondo 30 minutos. Isso vale tanto se a duração da consulta for variável no futuro quanto — em menor grau — mesmo com duração fixa, caso qualquer inserção não venha exatamente alinhada à grade gerada pelo `DisponibilidadeService` (bug, inserção manual, migração futura para duração variável por área). Um índice único não expressa uma regra de **intervalo x intervalo**; ele só expressa igualdade de um campo.

**Camada 2 — a garantia real: lock transacional por data + checagem de sobreposição por intervalo.**

1. Existe uma tabela auxiliar `agenda_locks` com uma linha por `data` (criada sob demanda, `data DATE PRIMARY KEY`). Ao iniciar uma tentativa de agendamento para uma data, o backend abre uma transação e executa `SELECT * FROM agenda_locks WHERE data = ? FOR UPDATE` (criando a linha antes se ainda não existir). Isso serializa **todas** as tentativas de agendamento para aquela mesma data — nenhuma outra transação consegue prosseguir além desse ponto até a primeira transação commitar ou fazer rollback.
2. Ainda dentro da mesma transação (portanto sem nenhuma outra requisição conseguindo intercalar), o backend executa a checagem de sobreposição real por intervalo:
   ```sql
   SELECT COUNT(*) FROM appointments
   WHERE data = :data
     AND status IN ('pending','confirmed')
     AND hora_inicio < :novo_hora_fim
     AND hora_fim   > :novo_hora_inicio
   ```
   Essa condição (`existente.inicio < novo.fim` E `existente.fim > novo.inicio`) é a fórmula padrão de detecção de sobreposição de intervalos e funciona **independentemente de duração ser fixa ou variável**, e independentemente dos horários de início coincidirem ou não.
3. Se a contagem for `0`, o `INSERT` do novo agendamento acontece na mesma transação e o commit libera o lock. Se for `> 0`, a transação é revertida e o cliente recebe "esse horário conflita com uma consulta já existente, escolha outro".
4. Índice de apoio para a performance dessa consulta: `INDEX (data, status, hora_inicio, hora_fim)` em `appointments`.

**Camada 3 — o índice único (`active_slot`) permanece como rede de segurança adicional**, não como mecanismo principal: protege contra o caso mais comum (mesmo horário de início exato) mesmo se, por algum bug futuro, uma inserção acontecer fora do fluxo transacional acima. Ele não substitui a Camada 2, mas custa nada mantê-lo.

**Regra final garantida**: nenhuma consulta pode ser inserida se seu intervalo `[hora_inicio, hora_fim)` sobrepõe, ainda que parcialmente, o intervalo de qualquer outra consulta `pending`/`confirmed` na mesma data — validado de forma atômica via lock transacional por data + checagem de intervalo, e não apenas por igualdade de horário de início.

## 4. Status das consultas

| Status | Uso na v1 | Observação |
|---|---|---|
| `CONFIRMED` | **Sim, é o status inicial padrão** | Agendamento é autosserviço: ao confirmar no formulário, já nasce confirmado (não há etapa manual de aprovação da advogada antes de valer). |
| `CANCELLED` | **Sim** | Definido pela administradora (ação "Cancelar" no painel) ou, futuramente, pelo próprio cliente via link no e-mail de confirmação (fora do escopo v1). |
| `COMPLETED` | **Sim, uso simples** | Botão manual no painel para marcar consultas passadas como realizadas — útil para histórico/relatórios da Dra. Valdizia, custo de implementação baixo. |
| `NO_SHOW` | **Sim, uso simples** | Mesmo mecanismo do `COMPLETED`, botão manual para marcar que o cliente não compareceu. |
| `PENDING` | **Reservado, não usado no modo automático** | Passa a ser o status inicial automaticamente **se** o modo de confirmação for trocado para manual no futuro (ver abaixo). |

**Decisão fechada nesta fase**: o modo inicial é **agendamento automático**. Ao concluir a reserva e a transação ser confirmada (camadas da seção 3), o agendamento nasce direto com `status = CONFIRMED`, e dispara imediatamente o e-mail de confirmação ao cliente e a notificação à Dra. Valdizia (seção 7).

A arquitetura já prevê, sem exigir mudança de schema depois, um modo configurável de confirmação:
- `configuracoes_globais.modo_confirmacao` — `ENUM('automatico','manual')`, `DEFAULT 'automatico'`.
- Quando `automatico` (modo v1): status inicial = `CONFIRMED`.
- Quando `manual` (evolução futura, **não implementada na Fase 1**): status inicial = `PENDING`, exigindo uma ação administrativa (`confirmar`/`recusar`) antes de virar `CONFIRMED`/`CANCELLED`, e o disparo de e-mail de confirmação ficaria condicionado a essa aprovação.

Isso deixa o campo pronto no banco desde já, mas nenhuma tela ou lógica de aprovação manual será construída nesta primeira versão — é só a coluna reservada para não exigir alterar o schema no futuro.

## 5. Banco de dados — schema detalhado

### `users`
| Campo | Tipo | Finalidade | Índice/Constraint |
|---|---|---|---|
| id | BIGINT UNSIGNED PK AI | Identificador | PK |
| name | VARCHAR(150) | Nome do administrador | — |
| email | VARCHAR(190) | Login | UNIQUE |
| password | VARCHAR(255) | Hash bcrypt | NOT NULL |
| role | ENUM('admin') DEFAULT 'admin' | Autorização; preparado para novos valores futuros | — |
| *(nota)* | — | **Fase 1 terá exatamente 1 registro** (Dra. Valdizia). O schema (tabela própria + coluna `role`) já suporta múltiplos administradores, mas nenhuma tela de gestão de usuários será construída agora — só a própria autenticação de quem já existe. | — |
| remember_token | VARCHAR(100) NULL | "Lembrar-me" | — |
| last_login_at | TIMESTAMP NULL | Auditoria de acesso | — |
| created_at / updated_at | TIMESTAMP | Controle | — |

### `areas_atuacao`
| Campo | Tipo | Finalidade | Índice/Constraint |
|---|---|---|---|
| id | BIGINT PK AI | Identificador | PK |
| slug | VARCHAR(80) | URL amigável | UNIQUE |
| nome | VARCHAR(120) | Ex. "Direito Previdenciário" | NOT NULL |
| descricao | TEXT NULL | Texto da página/card | — |
| icone | VARCHAR(60) NULL | Referência ao ícone de linha usado | — |
| ordem | SMALLINT DEFAULT 0 | Ordem de exibição | — |
| ativo | BOOLEAN DEFAULT TRUE | Oculta sem apagar | — |
| Relacionamento | — | `appointments.area_atuacao_id` → aqui | FK (RESTRICT) |

### `availability_rules`
| Campo | Tipo | Finalidade | Índice/Constraint |
|---|---|---|---|
| id | BIGINT PK AI | Identificador | PK |
| day_of_week | TINYINT | 0=domingo…6=sábado | INDEX (day_of_week, ativo) |
| hora_inicio | TIME | Início do intervalo padrão | NOT NULL |
| hora_fim | TIME | Fim do intervalo padrão | NOT NULL; CHECK (hora_fim > hora_inicio) |
| ativo | BOOLEAN DEFAULT TRUE | Permite desativar sem apagar | — |
| created_at / updated_at | TIMESTAMP | Controle | — |

### `availability_exceptions`
| Campo | Tipo | Finalidade | Índice/Constraint |
|---|---|---|---|
| id | BIGINT PK AI | Identificador | PK |
| data | DATE | Data específica afetada | INDEX (data, tipo) |
| tipo | ENUM('fechado_total','fechado_parcial','horario_especial') | Efeito sobre o dia | NOT NULL |
| hora_inicio | TIME NULL | Obrigatório se tipo ≠ fechado_total | — |
| hora_fim | TIME NULL | Idem | — |
| motivo | VARCHAR(255) NULL | Anotação livre da administradora | — |
| created_by | BIGINT UNSIGNED NULL | Quem criou o bloqueio | FK → users.id (SET NULL) |
| created_at / updated_at | TIMESTAMP | Controle | — |

### `holidays`
| Campo | Tipo | Finalidade | Índice/Constraint |
|---|---|---|---|
| id | BIGINT PK AI | Identificador | PK |
| nome | VARCHAR(120) | Ex. "Natal" | NOT NULL |
| mes | TINYINT | 1–12 | parte do índice |
| dia | TINYINT | 1–31 | parte do índice |
| ano | SMALLINT NULL | Preenchido só para feriados de data móvel de um ano específico (ex. Carnaval); NULL quando recorrente | — |
| recorrente_anual | BOOLEAN DEFAULT TRUE | Repete todo ano automaticamente | — |
| bloqueado | BOOLEAN DEFAULT TRUE | Permite, no futuro, um feriado que não bloqueie a agenda | — |
| UNIQUE | — | Evita duplicidade | UNIQUE (mes, dia, ano) |

### `configuracoes_globais` (singleton — 1 única linha)
| Campo | Tipo | Finalidade |
|---|---|---|
| id | BIGINT PK | Sempre 1 |
| duracao_consulta_min | SMALLINT DEFAULT 60 | Duração de cada consulta |
| intervalo_entre_consultas_min | SMALLINT DEFAULT 0 | Respiro entre consultas |
| antecedencia_minima_horas | SMALLINT DEFAULT 2 | Não permite agendar em cima da hora |
| antecedencia_maxima_dias | SMALLINT DEFAULT 60 | Não abre agenda longe demais |
| modo_confirmacao | ENUM('automatico','manual') DEFAULT 'automatico' | Ver seção 4 — v1 usa sempre `automatico`; coluna reservada para a evolução futura com aprovação manual, sem exigir alteração de schema depois |

### `agenda_locks` (suporte à Camada 2 da seção 3 — não é dado de negócio, é mecanismo de concorrência)
| Campo | Tipo | Finalidade | Índice/Constraint |
|---|---|---|---|
| data | DATE | Chave de lock — 1 linha por dia com pelo menos uma tentativa de agendamento | PK |
| created_at | TIMESTAMP | Controle | — |

A linha de um dia é criada (se não existir) e travada com `SELECT ... FOR UPDATE` no início de cada tentativa de agendamento naquela data, serializando as transações concorrentes para o mesmo dia antes da checagem de sobreposição.

### `appointments`
| Campo | Tipo | Finalidade | Índice/Constraint |
|---|---|---|---|
| id | BIGINT PK AI | Identificador | PK |
| area_atuacao_id | BIGINT UNSIGNED | Área escolhida | FK → areas_atuacao.id (RESTRICT) |
| nome_cliente | VARCHAR(150) | Dado do cliente | NOT NULL |
| email_cliente | VARCHAR(190) | Envio de confirmação | INDEX |
| telefone_cliente | VARCHAR(30) | Contato/WhatsApp | NOT NULL |
| observacao | TEXT NULL | Mensagem livre do cliente | — |
| data | DATE | Data do agendamento | parte dos índices abaixo |
| hora_inicio | TIME | Início do slot | parte dos índices abaixo |
| hora_fim | TIME | Fim do slot (derivado da duração) | — |
| status | ENUM('pending','confirmed','cancelled','completed','no_show') DEFAULT 'confirmed' | Ver seção 4 — default `confirmed` reflete o modo automático da v1 | INDEX (status) |
| active_slot | TINYINT GENERATED (`IF(status IN ('pending','confirmed'),1,NULL)`) VIRTUAL | Rede de segurança adicional (Camada 3, seção 3) | — |
| cancelled_reason | VARCHAR(255) NULL | Motivo do cancelamento | — |
| created_at / updated_at | TIMESTAMP | Controle | — |
| **UNIQUE** | — | Rede de segurança contra mesmo horário de início exato (Camada 3) | UNIQUE (data, hora_inicio, active_slot) |
| **INDEX** | — | **Suporte à checagem de sobreposição por intervalo — mecanismo principal (Camada 2, seção 3)** | INDEX (data, status, hora_inicio, hora_fim) |

### `admin_logs`
| Campo | Tipo | Finalidade | Índice/Constraint |
|---|---|---|---|
| id | BIGINT PK AI | Identificador | PK |
| user_id | BIGINT UNSIGNED NULL | Quem executou a ação | FK → users.id (SET NULL) |
| acao | VARCHAR(100) | Ex. "bloqueio_criado", "agendamento_cancelado" | — |
| detalhes | JSON NULL | Payload da ação | — |
| ip | VARCHAR(45) NULL | Auditoria | — |
| created_at | TIMESTAMP | Quando ocorreu | INDEX (user_id, created_at) |

## 6. Autenticação — especificação

- **Login**: formulário e-mail+senha em `/admin/login`, processado por Laravel Fortify.
- **Hash de senha**: bcrypt, cost factor 12 (`Hash::make`), nunca texto plano em log/e-mail.
- **Sessão**: driver `database`; cookies `HttpOnly`, `Secure` (exige HTTPS), `SameSite=Lax`; ID de sessão regenerado no login (evita session fixation); expiração por inatividade configurável (padrão 120 min).
- **Logout**: invalida a sessão atual no servidor (não é só apagar cookie no cliente); opção futura de "encerrar todas as sessões" já suportada nativamente pelo Fortify.
- **Proteção de rotas**: middleware `auth` em todo o grupo `/admin/*` (exceto a própria tela de login); Gates nomeados (`manage-agenda`, `manage-appointments`, `manage-config`) aplicados desde já nos controllers, mesmo com um único usuário — assim, criar um segundo admin com permissões restritas no futuro não exige refatorar controllers.
- **Recuperação de senha**: fluxo padrão Laravel — token assinado enviado por e-mail (mesmo provedor transacional da seção 7), expira em 60 minutos, uso único.
- **Proteção contra brute force**: throttle do Fortify por combinação e-mail+IP (ex. 5 tentativas/minuto com backoff), retornando erro genérico ("credenciais inválidas") sem revelar se o e-mail existe.
- **Autorização**: hoje só existe o papel `admin`; a coluna `role` e os Gates nomeados deixam a porta aberta para um papel mais restrito (ex. "secretária" só vê agenda, não mexe em configurações) sem mudança de schema.

## 7. E-mail — comparação e recomendação

| Critério | Resend | Brevo | Mailgun |
|---|---|---|---|
| Facilidade de integração | Muito alta — SDK/pacote oficial para Laravel, driver `resend` nativo | Alta — API própria ou SMTP relay | Média — API robusta, mas mais burocracia de configuração |
| Custo (free tier) | 3.000 e-mails/mês (100/dia) | 300 e-mails/dia | Free tier atual é limitado (sandbox exige lista de destinatários autorizados) |
| Confiabilidade/deliverability | Boa, focada em devs, exige verificar domínio (SPF/DKIM) | Boa, painel de entrega maduro, também exige verificação de domínio para melhor reputação | Muito usada por SaaS grandes, boa reputação quando configurada |
| API vs SMTP | Só API HTTPS — não depende de porta SMTP liberada na hospedagem | API **ou** SMTP relay — flexível | API e SMTP |
| Compatibilidade Laravel | Nativa (`MAIL_MAILER=resend`) | Via SMTP padrão do Laravel ou pacote comunitário | Via SMTP padrão ou pacote oficial |
| Idioma/suporte | Inglês | Painel e suporte em português (origem europeia com boa presença BR) | Inglês |
| Uso em hospedagem tipo Locaweb | Ótimo — não sofre com bloqueio de porta SMTP de saída | Ótimo se usar a API; SMTP pode sofrer bloqueio de porta em plano compartilhado | Mesmo risco de SMTP se não usar API |

**Recomendação: Resend.** Menor fricção de configuração com Laravel, não depende de porta SMTP (evita o risco já mapeado na seção 9/17 sobre hospedagem compartilhada), e o free tier cobre com folga o volume esperado de um escritório individual. **Brevo** fica como alternativa se a cliente preferir um painel de deliverability em português ou precisar de volume diário maior de graça.

Nenhuma conta/API key será configurada nesta fase — apenas recomendação registrada.

## 8. Homologação — estratégia (sem usar a máquina do desenvolvedor)

```
LOCAL (Docker/Sail no notebook do dev — só para desenvolver e testar)
   ↓ git push
GITHUB (branch principal + GitHub Actions rodando testes automatizados a cada push)
   ↓ deploy automático
HOMOLOGAÇÃO (ambiente público na nuvem, HTTPS, acessível de qualquer navegador — inclusive da casa da Dra. Valdizia)
   ↓ promoção do mesmo artefato, após validação da cliente
PRODUÇÃO (domínio oficial + hospedagem Locaweb definitiva)
```

Opções avaliadas para o ambiente de homologação (precisa PHP+MySQL+HTTPS público, sem depender do computador do desenvolvedor):

1. **Railway ou Render (recomendado)** — free/hobby tier, deploy automático via integração com GitHub a cada push, MySQL gerenciado incluso, HTTPS automático com URL pública (ex. `valdizia-homolog.up.railway.app`). A Dra. Valdizia acessa esse link de qualquer lugar, sem precisar de domínio próprio ainda.
2. **Locaweb definitiva contratada antecipadamente**, usando um subdomínio temporário (`homolog.dominio.com.br`) — replica o ambiente real de produção desde já, mas depende do domínio já estar comprado.
3. **Hospedagem PHP gratuita temporária** (ex. InfinityFree) — mais restritiva (sem cron confiável, HTTPS custom limitado); serve só para revisão visual estática, não para validar o fluxo completo de agendamento/e-mail.

**Recomendação**: opção 1 (Railway/Render) para a fase de homologação — grátis ou baixo custo, sem depender do notebook do desenvolvedor, com deploy automático a cada `git push`. Ao aprovar, o mesmo código é publicado na Locaweb definitiva (seção 9) para produção.

**Isolamento de bancos de dados por ambiente (regra fixa)**: existem três bancos MySQL fisicamente distintos e nunca compartilhados entre si:
1. **Local** — MySQL no Docker/Sail, na máquina do desenvolvedor, usado só durante o desenvolvimento.
2. **Homologação** — MySQL gerenciado pelo Railway/Render (ou pela Locaweb, se a opção 2 for escolhida), com seus próprios dados de teste. É o banco que a Dra. Valdizia efetivamente usa ao validar o sistema de casa.
3. **Produção** — MySQL da Locaweb definitiva, com os dados reais dos clientes dela.

O banco local do desenvolvedor **nunca** serve de banco de homologação da cliente, e o banco de homologação **nunca** é promovido/copiado como banco de produção — cada ambiente tem seu próprio schema aplicado via migrations e seus próprios dados, conectados apenas através das variáveis de ambiente (`DB_HOST`, `DB_DATABASE`, etc.) específicas de cada `.env`.

## 9. Locaweb — requisitos mínimos (plano ainda não contratado)

Requisitos técnicos que qualquer plano escolhido precisa atender:

| Requisito | Necessário para | Observação |
|---|---|---|
| PHP ≥ 8.2 (idealmente 8.3) | Laravel 11 | Confirmar versão exata disponível no plano antes de contratar |
| Extensões PHP: mbstring, openssl, pdo_mysql, tokenizer, xml, ctype, json, bcmath, fileinfo | Requisitos padrão do Laravel | Praticamente padrão em hospedagens PHP modernas, mas checar |
| MySQL 5.7+ (idealmente 8.0) | Banco de dados | Mesma versão do Docker local evita divergência |
| HTTPS/SSL | Segurança/LGPD | Certificado gratuito costuma vir incluso nos planos Locaweb |
| Composer disponível (via SSH) | Instalar dependências PHP | Se não houver, alternativa é gerar `vendor/` localmente/CI e enviar pronto via FTP/Git |
| Execução de migrations | Versionar schema do banco | Precisa de SSH (`php artisan migrate`) ou, na ausência, importar SQL exportado manualmente |
| Armazenamento persistente em disco | Logs, cache, sessões em arquivo | Padrão em qualquer hospedagem de arquivos |
| Cron configurável (1x/minuto) | `php artisan schedule:run` (lembretes futuros, limpeza) | Confirmar se o painel do plano permite agendar cron jobs |
| Variáveis de ambiente / `.env` | Configuração sem hardcode de credenciais | Hospedagem compartilhada tradicional não tem "painel de env vars"; `.env` fica como arquivo fora do webroot público, com permissão restrita |

Tipos de hospedagem Locaweb avaliados:
- **Hospedagem de Sites compartilhada (linha Linux, planos Hospedagem I/II)** — compatível com PHP+MySQL nativamente; viável **se** o plano específico oferecer SSH+Composer+cron (varia por tier — confirmar antes de contratar).
- **Cloud Server/VPS Locaweb** — acesso root, qualquer versão de PHP/MySQL, cron e Composer sem restrição; elimina toda incerteza acima, ao custo de mais gestão de servidor e preço maior.
- **Não compatível**: hospedagem apenas estática ou linha Windows/ASP clássica — fora de cogitação para esta stack.

Nenhuma suposição foi feita sobre um plano já existente — a contratação e confirmação desses pontos junto ao comercial da Locaweb é decisão pendente (seção final).

## 10. LGPD

- **Dados coletados**: nome, e-mail, telefone e observação livre (pode conter dado sensível se o cliente descrever o caso jurídico) no formulário público de agendamento; e-mail/senha da própria administradora (dado da profissional, não de terceiro).
- **Finalidade**: exclusivamente viabilizar o agendamento e o contato entre cliente e escritório — sem uso para marketing sem consentimento separado e específico.
- **Minimização**: só os campos citados + área de interesse são coletados nesta fase; nenhum dado adicional (CPF, endereço) a menos que a própria Dra. Valdizia solicite formalmente no futuro.
- **Armazenamento**: MySQL com acesso restrito a usuários `role=admin`; nenhuma rota pública expõe dados de outros clientes (o endpoint de disponibilidade retorna só horários livres, nunca identificação de quem ocupa os demais).
- **Acesso administrativo**: somente a Dra. Valdizia (e futuros administradores autorizados) visualiza dados completos; toda visualização/cancelamento sensível fica registrada em `admin_logs`.
- **Retenção**: o prazo definitivo de retenção deverá ser definido pela cliente/responsável pelo tratamento, considerando finalidade, obrigações legais e orientação jurídica aplicável. Não há suposição de prazo fixo (ex. "5 anos") nesta fase, e **nenhuma rotina automática de exclusão de dados será criada na primeira versão** enquanto essa política não for definida — o sistema apenas armazena os dados de forma segura e auditável, pronto para receber uma política de retenção/exclusão automatizada assim que a cliente a definir.
- **Exclusão**: mecanismo administrativo (ou solicitação via e-mail) para excluir/anonimizar dados de um titular mediante pedido, conforme art. 18 da LGPD.
- **Política de privacidade**: página pública `/politica-de-privacidade` documentando os pontos acima.
- **Consentimento**: checkbox obrigatório ("Li e concordo com a Política de Privacidade") no formulário de agendamento antes de habilitar a confirmação.

## 11. Segurança — checklist técnico

| Área | Medida |
|---|---|
| Autenticação | Bcrypt (cost 12), Fortify, regeneração de sessão no login |
| Autorização | Middleware `auth` + Gates nomeados em todas as rotas `/admin/*` |
| Sessão | Cookies `HttpOnly`+`Secure`+`SameSite=Lax`, driver `database`, expiração por inatividade |
| CSRF | Token automático do Laravel em todo formulário (Blade `@csrf`) |
| XSS | Escapamento automático do Blade (`{{ }}`); nunca `{!! !!}` com dado de usuário |
| SQL Injection | Eloquent/Query Builder parametrizado; nenhuma query raw concatenando input |
| Brute force | Throttle de login (Fortify) por e-mail+IP |
| Rate limiting | Throttle também no endpoint público de agendamento e no de horários disponíveis |
| Validação | Form Requests do Laravel validando todo input server-side (nunca confiar só no frontend) |
| Upload futuro | Ainda não previsto no escopo; se vier a existir (ex. anexar documento), validar tipo/tamanho e nunca servir arquivo enviado diretamente do diretório de upload sem sanitização |
| Exposição de dados | `.env` fora do webroot público e fora do Git; `APP_DEBUG=false` em produção; mensagens de erro genéricas ao público |
| Logs | `admin_logs` para ações administrativas sensíveis (bloqueios, cancelamentos, mudança de configuração) |
| Backups | Depende do plano Locaweb contratado (seção 9); complementar com `mysqldump` agendado via cron para armazenamento externo |

## 12. Rotas

**Públicas**
```
GET  /
GET  /sobre
GET  /areas-de-atuacao
GET  /areas-de-atuacao/{slug}
GET  /agendamento
POST /agendamento
GET  /agendamento/horarios-disponiveis   (AJAX)
GET  /contato
GET  /politica-de-privacidade
GET  /termos-de-uso
```

**Administrativas** (prefixo `/admin`, protegidas por `auth`)
```
GET  /admin/login
POST /admin/login
POST /admin/logout
GET  /admin                        (dashboard)
GET  /admin/agenda                 (visão de calendário)
GET  /admin/agendamentos           (lista + filtros)
GET  /admin/agendamentos/{id}      (detalhe)
POST /admin/agendamentos/{id}/cancelar
POST /admin/agendamentos/{id}/concluir
POST /admin/agendamentos/{id}/no-show
GET  /admin/configuracoes          (horários da semana + duração/intervalo/antecedência)
PUT  /admin/configuracoes
GET  /admin/feriados
POST /admin/feriados
DELETE /admin/feriados/{id}
GET  /admin/bloqueios
POST /admin/bloqueios
DELETE /admin/bloqueios/{id}
GET  /admin/perfil                 (trocar senha — opcional v1)
```

## 13. Componentes Blade previstos

**Camada pública**
- `layout.app` (estrutura base, head, fontes, paleta)
- `components.header`
- `components.footer`
- `components.hero`
- `components.feature-strip` (4 diferenciais)
- `components.area-card`
- `components.section-sobre`
- `components.cta-band`
- `components.badge-label` (label pequena dourada com tracking)
- `components.button` (variantes: sólido dourado / sólido verde-escuro)

**Agendamento**
- `components.booking.calendar-picker`
- `components.booking.time-slot-grid`
- `components.booking.booking-form`
- `components.booking.booking-confirmation`

**Camada administrativa**
- `layout.admin`
- `components.admin.sidebar`
- `components.admin.topbar`
- `components.admin.stat-card`
- `components.admin.appointments-table`
- `components.admin.appointment-detail`
- `components.admin.weekly-hours-editor`
- `components.admin.exceptions-manager`
- `components.admin.holidays-manager`
- `components.admin.config-form`

## 14. Critérios de aceite por módulo

**Disponibilidade/Agenda**
- Cliente só consegue selecionar horários que passam por todas as camadas do pipeline (seção 2) — nenhum horário bloqueado, feriado ou já ocupado aparece como opção.
- Alterar um horário padrão no admin reflete imediatamente na disponibilidade pública, sem necessidade de deploy.

**Concorrência e sobreposição**
- Duas submissões simultâneas para o mesmo `data`+`hora_inicio` resultam em exatamente 1 agendamento `confirmed` e 1 erro amigável para a segunda tentativa — nunca 2 confirmados.
- Duas submissões simultâneas com horários que se sobrepõem parcialmente mas têm `hora_inicio` diferente (ex. 14:00–15:00 e 14:30–15:30) também resultam em exatamente 1 agendamento confirmado — a segunda é rejeitada por conflito de intervalo, não só por igualdade de horário de início.

**Administração**
- Administradora consegue bloquear e desbloquear uma data específica, e o efeito aparece imediatamente na disponibilidade pública.
- Administradora consegue cadastrar e remover um feriado.
- Administradora consegue alterar o horário de atendimento de qualquer dia da semana.
- Administradora consegue visualizar detalhes completos de um agendamento e cancelá-lo.

**Autenticação**
- Rotas `/admin/*` (exceto login) retornam redirecionamento para login quando não autenticado.
- 5 tentativas de login incorretas em menos de 1 minuto bloqueiam temporariamente novas tentativas.

**E-mail**
- Ao confirmar um agendamento, a Dra. Valdizia recebe e-mail com nome, telefone, e-mail, área, data, horário e observação; o cliente recebe e-mail de confirmação separado.

**Design/Responsividade**
- Em desktop, a home reproduz fielmente composição, hierarquia, paleta e proporções da referência.
- Em tablet e mobile, a mesma identidade visual e ordem de seções se mantém, com reflow de grid sem cards/elementos inventados.

**LGPD**
- Formulário de agendamento não avança sem o checkbox de consentimento marcado.
- Página de política de privacidade está publicada e acessível a partir do footer.

---

## Resultado desta fase

**1. Solução final para evitar sobreposição de consultas**: lock transacional por data (`agenda_locks` + `SELECT ... FOR UPDATE`) que serializa tentativas concorrentes no mesmo dia, seguido — dentro da mesma transação — de uma checagem de sobreposição por fórmula de intervalo (`existente.hora_inicio < novo.hora_fim AND existente.hora_fim > novo.hora_inicio`), que funciona independentemente de duração fixa ou variável e independentemente dos horários de início coincidirem. O índice único em `active_slot` permanece só como rede de segurança adicional para o caso de mesmo horário de início exato — não é mais tratado como o mecanismo principal.

**2. Arquitetura final aprovada nesta rodada**:
- Agendamento automático (`status = CONFIRMED` direto na criação), com e-mail de confirmação ao cliente e notificação à Dra. Valdizia disparados imediatamente. Campo `configuracoes_globais.modo_confirmacao` (`automatico`/`manual`) já reservado no schema para uma futura troca para aprovação manual, sem exigir nova migration de schema quando isso for implementado.
- Apenas 1 usuária administrativa (Dra. Valdizia) na Fase 1; schema já suporta múltiplos administradores, mas nenhuma tela de gestão de usuários será construída agora.
- Retenção de dados LGPD sem prazo fixo assumido: "o prazo definitivo de retenção deverá ser definido pela cliente/responsável pelo tratamento, considerando finalidade, obrigações legais e orientação jurídica aplicável" — nenhuma exclusão automática será implementada nesta versão.
- Infraestrutura mantida em 3 ambientes com bancos MySQL fisicamente isolados: Local (Docker) → GitHub (versionamento) → Homologação (Railway/Render, banco próprio) → Produção (Locaweb, banco próprio). Nenhum banco é reaproveitado entre ambientes.

**3. Decisões que ainda precisam da sua aprovação/input**:
- Plano Locaweb definitivo e confirmação com o comercial de que ele oferece SSH+Composer+cron (ou decisão por Cloud Server/VPS para eliminar essa incerteza).
- Domínio oficial da cliente (ainda não informado).
- Política de retenção/exclusão de dados definitiva (a definir pela cliente/orientação jurídica dela) — necessária antes de qualquer rotina automática de exclusão ser implementada em fase futura.

**4. Riscos técnicos**:
- O lock transacional por data (`agenda_locks` + `FOR UPDATE`) precisa que toda gravação de agendamento passe obrigatoriamente pelo `AgendamentoService` — qualquer rota ou script futuro que insira direto na tabela `appointments` sem passar por esse fluxo reabre a janela de corrida.
- Deliverability de e-mail depende de verificação de domínio (SPF/DKIM) — sem isso, e-mails do Resend podem cair em spam até o domínio da cliente ser configurado.
- Confirmação tardia do plano Locaweb (SSH/Composer/cron) pode forçar troca para Cloud Server/VPS já perto do fim do projeto, com impacto de custo/cronograma.
- Ambiente de homologação (Railway/Render) usa infraestrutura diferente da Locaweb final — qualquer particularidade específica da Locaweb só será validada de fato na promoção para produção.

**5. O que será implementado na Fase 1** (mediante autorização):
- Estrutura do projeto Laravel (`composer.json`, estrutura de pastas já descrita na seção 3 do plano original).
- Docker local (Sail) com MySQL 8 e Mailpit.
- Migrations reais para todas as tabelas especificadas (`users`, `areas_atuacao`, `availability_rules`, `availability_exceptions`, `holidays`, `configuracoes_globais`, `appointments`, `agenda_locks`, `admin_logs`), incluindo a coluna gerada `active_slot`, os índices únicos/compostos e o campo `modo_confirmacao`.
- Seeders com a configuração inicial real (seg-sex 09h–18h, sáb/dom/feriados bloqueados, `modo_confirmacao = automatico`).
- `DisponibilidadeService` e `AgendamentoService` (lock + checagem de sobreposição), ainda sem interface visual — só a lógica de backend e testes automatizados cobrindo os critérios de aceite da seção 14 (inclusive o caso de sobreposição 14:00–15:00 vs 14:30–15:30).

**Ainda aguardando autorização explícita para iniciar a Fase 1.**

