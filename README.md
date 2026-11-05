# Site Dra. Valdizia Melo — Advocacia e Assessoria Jurídica

Site institucional com sistema de agendamento de consultas e painel administrativo para a Dra. Valdizia Melo (Direito Previdenciário, Cível e de Família).

> Planejamento técnico completo (arquitetura, schema, decisões e riscos) em [`PLANO-TECNICO.md`](./PLANO-TECNICO.md). Este README cobre apenas o dia a dia de desenvolvimento.

**Status atual**: fluxo público de agendamento e painel administrativo completos — autenticação (com recuperação de senha), agenda (consultas, cancelamento/conclusão/não comparecimento, busca por nome/e-mail/telefone), configuração de disponibilidade (horários semanais, exceções, feriados, bloqueios), e-mails transacionais (confirmação de agendamento, cancelamento manual e cancelamento por alteração de disponibilidade) e headers de segurança/CSP. **Ainda não há provedor de e-mail real configurado para produção nem ambiente de produção/Locaweb** — ver "O que ainda não foi implementado" abaixo.

## Stack

- PHP 8.3
- Laravel 13
- MySQL 8.4
- Blade + Tailwind CSS 4 + Alpine.js
- Docker / Laravel Sail para desenvolvimento local
- Vite para build de assets front-end
- PHPUnit para testes automatizados

## O que já foi implementado

### Site público
- **Homepage** institucional (Blade + Tailwind), com áreas de atuação, e link para a Política de Privacidade (conteúdo placeholder — ver seção abaixo).
- **Agendamento público** (`/agendamento`): wizard em 5 passos (Área → Data → Horário → Dados → Confirmação). Dados obrigatórios do cliente: nome, e-mail, telefone e **assunto** da consulta (observação continua opcional) — validados no backend (`AgendarConsultaRequest` e `AppointmentService::agendar()`), nunca só no frontend. Ao confirmar com sucesso, o cliente recebe um **e-mail de confirmação** (`ConsultaConfirmadaMail`) com área, assunto, data e horário — enviado só depois que a transação de banco já comitou, nunca dentro dela.
- **Disponibilidade real**, calculada pelo `AvailabilityService` (**única fonte de verdade**, usada pelo fluxo público, pela agenda administrativa e pela configuração de disponibilidade): expediente semanal → exceções da data → feriado → agendamentos já existentes.
- **Proteção contra concorrência**: lock transacional por data (`agenda_locks` + `SELECT ... FOR UPDATE`) + revalidação da disponibilidade dentro da transação + índice único como rede de segurança adicional. Alterações administrativas de disponibilidade reaproveitam o mesmo lock, adquirido em ordem cronológica quando mais de uma data é afetada, para nunca gerar deadlock com uma reserva pública concorrente.
- **URL de confirmação assinada e temporária** (`URL::temporarySignedRoute()`, válida por 2 dias, middleware `signed`).
- **Tratamento explícito de falha de API** no wizard (`resources/js/agendamento.js`): nunca confunde erro técnico com "sem horários".
- **Rate limiting**: `throttle:60,1` nos endpoints de disponibilidade/horários, `throttle:10,1` no envio do agendamento e no login administrativo.
- **Política de Privacidade**: página em `/politica-de-privacidade`, conteúdo placeholder — precisa de revisão jurídica antes de produção.

### Painel administrativo (`/admin`)
- **Login** (`/admin/login`) com e-mail/senha, hash nativo do Laravel, rate limiting por e-mail+IP (5 tentativas) além do `throttle` da rota, mensagem genérica de erro ("Credenciais inválidas.") que nunca revela se o e-mail existe. Sessão sempre regenerada após login bem-sucedido (previne session fixation); logout invalida a sessão e regenera o token CSRF.
- **Recuperação de senha** (`/admin/forgot-password` → `/admin/reset-password/{token}`): usa o `PasswordBroker` nativo do Laravel (token com hash, expiração em 60 min, uso único — nada de criptografia própria). A resposta pública é **sempre a mesma mensagem genérica**, exista ou não conta associada ao e-mail informado, para não permitir enumeração de usuários. E-mail enviado via `ResetPasswordMail`, com a mesma identidade visual dos demais e-mails do projeto.
- **Autorização**: todas as rotas de `/admin/*` (exceto login) exigem `auth` + `Gate` `access-admin-panel` (baseado em `users.role`) — nunca confia em dado vindo do frontend para decidir permissão.
- **Dashboard**: números reais do banco (consultas de hoje, confirmadas, futuras, áreas ativas).
- **Agenda** (`/admin/agenda`): visão diária/semanal, filtros por data/status/área, **busca por nome/e-mail/telefone** (condição adicional aos filtros, executada no banco), detalhe de cada consulta (nome, e-mail, telefone, assunto, observação, status, histórico) buscado sob demanda — nunca pré-carregado na listagem (minimização de dados pessoais). Ações **cancelar**, **concluir** e **marcar não comparecimento**, cada uma como uma rota explícita (o backend nunca aceita um campo `status` arbitrário vindo do cliente). `concluir`/`não comparecimento` só são permitidos depois que a consulta já terminou (`data + hora_fim <= agora`) — evita liberar por engano um horário futuro para um novo cliente. **Cancelar** envia ao cliente o mesmo e-mail de cancelamento (`ConsultaCanceladaMail`) usado pelo cancelamento por disponibilidade abaixo — um único template para os dois caminhos.
- **Disponibilidade** (`/admin/disponibilidade`): a Dra. configura, sem tocar em código:
  - **Horários semanais** — hora inicial/final e ativo/inativo por dia da semana;
  - **Exceções de horário** — uma data específica com janela diferente da regra padrão;
  - **Feriados** — data(s) tratada(s) como indisponíveis;
  - **Bloqueios de dia inteiro** — "sem atendimento" numa data, com motivo opcional.

  Qualquer alteração que afetaria consultas já agendadas **nunca é aplicada silenciosamente**: o sistema detecta o conflito (reaproveitando o próprio `AvailabilityService`, nunca uma segunda lógica de disponibilidade), mostra exatamente quais consultas seriam canceladas (data, horário, cliente) e só executa após confirmação explícita. Ao confirmar: a alteração de disponibilidade e o cancelamento das consultas afetadas acontecem na **mesma transação** (nunca um estado parcial); o cliente recebe um e-mail de cancelamento (uma falha de envio nunca desfaz a operação, já commitada); e tudo fica registrado no `AdminLog` (quem, o quê, quando, estado anterior/novo).

## E-mails transacionais (Fase 4E)

Três eventos disparam e-mail ao cliente, sempre pela mesma arquitetura: `AppointmentNotificationService` (`app/Services/AppointmentNotificationService.php`), chamado **depois** que a operação de banco correspondente já comitou (nunca dentro de uma `DB::transaction()`) — uma falha de envio é registrada via `Log::warning()` e nunca desfaz nem bloqueia a operação já persistida:

- **Confirmação de agendamento** (`ConsultaConfirmadaMail`) — disparado por `AppointmentController::store()`.
- **Cancelamento manual** (`ConsultaCanceladaMail`) — disparado por `AdminAgendaController::cancelar()`.
- **Cancelamento por alteração de disponibilidade** (`ConsultaCanceladaMail`, mesmo Mailable do item anterior) — disparado por `AdminAvailabilityService`, um e-mail por consulta afetada; a falha de um envio nunca impede a tentativa de envio dos demais.

Cada `Mailable` tem versão HTML e texto simples (`resources/views/emails/`), sem JS/CSS externo, seguindo a identidade visual do site (verde profundo, off-white, bege, dourado). O e-mail de cancelamento usa texto neutro ("Sua consulta foi cancelada") em vez de atribuir um motivo específico, já que é reaproveitado pelos dois caminhos de cancelamento.

## Segurança (Fase 4F)

- **Headers de segurança + CSP** (`App\Http\Middleware\SecurityHeaders`, aplicado a todo o grupo `web` — inclusive respostas geradas a partir de uma exceção, como o redirect de guest/auth, via `Exceptions::respond()` em `bootstrap/app.php`): `X-Content-Type-Options: nosniff`, `Referrer-Policy: strict-origin-when-cross-origin`, `X-Frame-Options: SAMEORIGIN`, `Permissions-Policy` restritiva, e um `Content-Security-Policy` com `default-src 'self'` (todo o front-end é self-hosted via Vite — sem CDN, sem Google Fonts externo).
  - **Limitação conhecida e documentada**: o projeto usa a build padrão do Alpine.js (não a `@alpinejs/csp`), que exigiu duas concessões pontuais — confirmadas necessárias via Playwright real, não só leitura de código: `script-src` inclui `'unsafe-eval'` (Alpine avalia expressões de `x-data`/`@click`/etc. via `new Function()`), e `style-src` inclui `'unsafe-inline'` (`x-show`/`x-transition` — usados até no header público, presente em toda página — escondem/mostram elementos escrevendo direto em `element.style.*` via JS, o que a CSP trata como "inline style" mesmo sem nenhum `style=""` no HTML-fonte). Migrar para a build CSP do Alpine exigiria reescrever `x-data`/`x-show`/`@click` em várias views (refatoração grande, fora do escopo desta fase).
- **Session**: `HttpOnly` e `SameSite=Lax` (padrão do Laravel), regeneração de sessão após login, invalidação após logout — nada alterado, já existia desde a Fase 4A. `SESSION_SECURE_COOKIE` continua orientado por ambiente (ver `.env.example`).
- **Recuperação de senha**: ver seção do painel administrativo acima.
- **CSRF**: auditado — todo formulário POST/PUT/PATCH/DELETE administrativo tem `@csrf`; `VerifyCsrfToken`/`ValidateCsrfToken` nunca foi desabilitado.
- **XSS**: nenhum `{!! !!}` com dado de cliente em nenhuma view/e-mail — tudo passa por `{{ }}` (escape padrão do Blade) ou por `x-text` do Alpine (que usa `textContent`, nunca `innerHTML`). Testes de regressão cobrem payload `<script>` em nome/assunto/observação.
- **SQL injection**: 100% Eloquent/query builder com bindings, inclusive a busca da Fase 4D (`LIKE`/`whereRaw` sempre parametrizados, nunca concatenação).
- **Open redirect**: nenhum parâmetro `next`/`redirect` controlado pelo cliente existe na aplicação; `redirect()->intended()` do login usa a URL interna que o próprio Laravel capturou antes do redirect para o login, nunca um valor arbitrário do usuário.

## O que ainda não foi implementado

- Provedor de e-mail real em produção — a arquitetura de mail já existe e funciona (capturada pelo Mailpit em desenvolvimento); falta configurar um provedor real (Resend ou SMTP do domínio) nas variáveis de ambiente de produção.
- Lembrete automático de consulta, remarcação e cancelamento pelo próprio cliente (fora do escopo atual).
- Configuração de agenda pelo painel: duração da consulta e intervalo entre consultas continuam centralizados em `configuracoes_globais` (55 min + 5 min, não editável pela Dra. nesta fase).
- Múltiplos perfis administrativos (hoje só existe o papel `admin`).
- Migração do Alpine.js para a build `@alpinejs/csp` (permitiria remover `'unsafe-eval'` da CSP) — refatoração grande, avaliar conforme necessidade real.
- Configuração de ambiente de produção/Locaweb (hospedagem, domínio, certificado, variáveis de produção) — ver checklist abaixo.

## Checklist de produção (preparar na Fase 6)

Nada disto é configurado nesta fase — só documentado para quando a Locaweb for configurada:

- `APP_ENV=production`, `APP_DEBUG=false` (nunca deixar `true` em produção — expõe stack trace/SQL/caminhos internos).
- `APP_URL` com o domínio real, sempre `https://`.
- `SESSION_SECURE_COOKIE=true` (só funciona com HTTPS de fato configurado).
- HTTPS obrigatório (certificado válido no domínio real).
- `MAIL_*` apontando para um provedor real (Resend ou SMTP do domínio) — nunca a senha pessoal da cliente.
- `DB_*` com host/credenciais reais do banco de produção.
- Gerar um `.env` real no servidor (nunca commitar) com `APP_KEY` própria de produção.
- Permissões de escrita em `storage/` e `bootstrap/cache/`.

## Requisitos

- Docker Desktop (com Docker Compose v2) instalado e em execução
- Git

Não é necessário ter PHP, Composer ou Node instalados na máquina — tudo roda dentro dos containers (Sail já inclui Node/npm).

## Instalação (primeira vez)

```bash
# 1. Clonar o repositório e entrar na pasta
git clone <url-do-repositorio>
cd Site-Valdizia

# 2. Copiar o arquivo de variáveis de ambiente
cp .env.example .env

# 3. Construir as imagens Docker (primeira vez ou após mudanças no Dockerfile)
docker compose build

# 4. Subir os containers (app Laravel, MySQL, Mailpit)
docker compose up -d

# 5. Instalar as dependências PHP dentro do container
docker compose exec laravel.test composer install

# 6. Gerar a chave da aplicação (se o .env copiado não tiver uma)
docker compose exec laravel.test php artisan key:generate

# 7. Rodar as migrations
docker compose exec laravel.test php artisan migrate

# 8. Popular o banco com dados de desenvolvimento (ver seção "Seeders" abaixo)
docker compose exec laravel.test php artisan db:seed

# 9. Instalar as dependências JS e gerar o build de assets
docker compose exec laravel.test npm install
docker compose exec laravel.test npm run build
```

A aplicação fica disponível em `http://localhost`, o painel administrativo em `http://localhost/admin/login`, e o Mailpit (captura de e-mails locais, sem envio real) em `http://localhost:8025`.

### Nota sobre rede corporativa com inspeção TLS (proxy/antivírus)

Se a sua rede fizer inspeção HTTPS (comum em antivírus corporativos como Kaspersky, ou proxies de empresa), o `docker compose build` pode falhar com erro de certificado ao baixar pacotes. Nesse caso:

1. Exporte o certificado raiz da sua ferramenta de segurança em formato `.crt`.
2. Coloque o arquivo em `docker/8.3/certs/` (essa pasta é ignorada pelo Git — o certificado nunca é versionado).
3. Rode `docker compose build` novamente.

A pasta vazia não tem nenhum efeito — isso só é necessário se você realmente enfrentar erros de certificado.

## Comandos do dia a dia

| Ação | Comando |
|---|---|
| Iniciar os containers | `docker compose up -d` |
| Parar os containers | `docker compose down` |
| Ver logs da aplicação | `docker compose logs -f laravel.test` |
| Acessar o shell do container da aplicação | `docker compose exec laravel.test bash` |
| Acessar o MySQL via cliente de linha de comando | `docker compose exec mysql mysql -u sail -p laravel` (senha em `DB_PASSWORD` no `.env`) |
| Rodar migrations | `docker compose exec laravel.test php artisan migrate` |
| Desfazer a última migration | `docker compose exec laravel.test php artisan migrate:rollback` |
| Recriar o banco do zero + seeders | `docker compose exec laravel.test php artisan migrate:fresh --seed` |
| Rodar os seeders (sem recriar o schema) | `docker compose exec laravel.test php artisan db:seed` |
| Rodar toda a suíte de testes | `docker compose exec laravel.test php artisan test` |
| Rodar só um arquivo de teste | `docker compose exec laravel.test php artisan test --filter=AdminAvailabilityTest` |
| Build de produção dos assets (Vite) | `docker compose exec laravel.test npm run build` |
| Servidor de desenvolvimento do Vite (hot reload) | `docker compose exec laravel.test npm run dev` |
| Abrir o Tinker (console interativo) | `docker compose exec laravel.test php artisan tinker` |

## Seeders (dados de desenvolvimento)

O comando `php artisan db:seed` cria:

- **Usuária administradora de desenvolvimento**: `dev@valdiziamelo.local` / `TrocarEssaSenha123!`
  ⚠️ **Credencial de desenvolvimento apenas — nunca a senha real da cliente.** Deve ser trocada antes de qualquer uso fora do ambiente local/homologação (via Tinker, ou pela tela de login já pedindo a senha real depois de trocada no banco).
- 3 áreas de atuação (Direito Previdenciário, Direito de Família, Direito Civil), todas ativas.
- **Expediente padrão**: segunda a quinta 09:00–18:00, sexta 09:00–17:00; sábado e domingo nascem sem atendimento (sem regra cadastrada). Reconfigurável pela Dra. em `/admin/disponibilidade` a qualquer momento.
- Feriados nacionais fixos (recorrência anual) **+ o feriado municipal de Ferraz de Vasconcelos (14/10)**. Feriados móveis (Carnaval, Sexta-feira Santa, Corpus Christi) precisam ser cadastrados manualmente todo ano pela tela de Feriados — não são chutados no seeder.
- Configuração global: consulta de **55 minutos** com **5 minutos** de intervalo, antecedência mínima de 2h e máxima de 60 dias, modo de confirmação automático. Duração e intervalo não são editáveis pelo painel nesta fase (ficam centralizados aqui, de propósito).

## Variáveis de ambiente

Ver `.env.example` para a lista completa e comentada. Nenhuma credencial real está commitada — apenas valores de desenvolvimento local (Sail/MySQL/Mailpit). Destaques:

- `APP_TIMEZONE=America/Sao_Paulo`, `APP_LOCALE=pt_BR` — timezone e locale da aplicação já configurados para o Brasil.
- `DB_*` — em desenvolvimento aponta para o serviço `mysql` do `compose.yaml`; em produção deve apontar para o MySQL real via variáveis de ambiente do próprio host.
- `MAIL_*` — em desenvolvimento usa o Mailpit (`MAIL_MAILER=smtp`, `MAIL_HOST=mailpit`), sem envio real. Em produção, apontar para um provedor real (Resend ou SMTP do domínio) através destas mesmas variáveis — o código (`App\Mail\ConsultaConfirmadaMail`, `App\Mail\ConsultaCanceladaMail`) não muda, só a configuração.
- `QUEUE_CONNECTION` — hoje os e-mails são enviados de forma síncrona logo após a transação de banco confirmar (uma falha de envio é registrada em log e nunca desfaz a operação). A tabela `jobs` já existe caso o envio precise ser movido para fila no futuro.
- `SESSION_SECURE_COOKIE` — documentado no próprio `.env.example`: definir como `true` em produção (HTTPS), deixado sem valor em desenvolvimento local (`http://localhost`).

## Estrutura do projeto

```
app/
  Exceptions/
    HorarioIndisponivelException.php              # slot deixou de estar disponível no momento de agendar
    TransicaoDeStatusInvalidaException.php        # ação de status inválida pro estado/tempo atual da consulta
    AlteracaoDeAgendaAfetaConsultasException.php  # alteração administrativa afeta consultas — exige confirmação
  Http/
    Middleware/SecurityHeaders.php                  # headers de segurança + CSP (grupo "web")
    Controllers/AppointmentController.php          # fluxo público de agendamento
    Controllers/Admin/AdminAuthController.php       # login/logout do painel
    Controllers/Admin/AdminPasswordResetController.php # recuperação de senha (PasswordBroker nativo)
    Controllers/Admin/AdminDashboardController.php  # dashboard
    Controllers/Admin/AdminAgendaController.php     # agenda administrativa (consultas)
    Controllers/Admin/AdminAvailabilityController.php # disponibilidade administrativa (horários/exceções/feriados/bloqueios)
    Requests/AgendarConsultaRequest.php             # validação do formulário público (inclui área ativa e assunto)
    Requests/Admin/AdminLoginRequest.php            # autenticação + rate limit do login admin
    Requests/Admin/AdminForgotPasswordRequest.php   # validação do e-mail na solicitação de recuperação
    Requests/Admin/AdminResetPasswordRequest.php    # validação da nova senha
  Mail/ConsultaConfirmadaMail.php                   # e-mail de confirmação de novo agendamento
  Mail/ConsultaCanceladaMail.php                    # e-mail de cancelamento (manual ou por alteração administrativa)
  Mail/ResetPasswordMail.php                        # e-mail de redefinição de senha
  Models/
    User, AreaAtuacao, AvailabilityRule, AvailabilityException,
    Holiday, ConfiguracaoGlobal, Appointment, AgendaLock, AdminLog
  Services/
    AvailabilityService.php         # única fonte de verdade da disponibilidade (pipeline documentado no código)
    AppointmentService.php          # criação/cancelamento/transição de status de agendamento
    AdminAvailabilityService.php    # orquestra alterações administrativas: detecta conflito, cancela, notifica, audita
    AppointmentNotificationService.php # único ponto de envio de e-mail transacional, sempre pós-persistência
database/
  migrations/    # schema versionado (ver PLANO-TECNICO.md seção 5 para o desenho completo)
  seeders/       # dados iniciais de desenvolvimento (ver seção "Seeders" acima)
docker/
  8.3/           # build context do container da aplicação (baseado no runtime oficial do Sail)
resources/
  views/pages/       # homepage, agendamento (wizard), confirmação, política de privacidade
  views/admin/        # login, recuperação de senha, dashboard, agenda e disponibilidade do painel
  views/emails/        # e-mails de confirmação, cancelamento e redefinição de senha (HTML + texto simples)
  js/agendamento.js    # wizard de agendamento público (Alpine.js) + tratamento de falha de API
  js/agenda-admin.js   # modal de detalhe/ações da agenda administrativa
tests/
  Concerns/SeedsAgendaFixtures.php
  Feature/AvailabilityServiceTest.php
  Feature/AppointmentServiceTest.php
  Feature/AppointmentConcurrencyTest.php
  Feature/AgendamentoPublicoTest.php
  Feature/AdminAuthTest.php
  Feature/AdminAgendaTest.php
  Feature/AdminAvailabilityTest.php
  Feature/AdminPasswordResetTest.php
  Feature/SecurityHeadersTest.php
PLANO-TECNICO.md   # arquitetura, decisões, riscos e especificação completa do projeto
```

## Testes automatizados

A suíte tem **164 testes** (437 assertions). Cobre, entre outras coisas:

- toda a resolução de disponibilidade (expediente, exceções, feriados, bloqueios, sobreposição, duração/intervalo configuráveis);
- concorrência: lock transacional por data impede dupla reserva (com processos concorrentes reais via `pcntl_fork` quando disponível);
- fluxo público completo (validação, criação, confirmação assinada e temporária, rate limit, e-mail de confirmação com `Mail::fake()`, falha de envio não desfaz o agendamento, payload `<script>` em nome/assunto sempre escapado);
- autenticação e autorização administrativa (Gate, rate limit de login, mensagens genéricas, sessão regenerada no login, sessão invalidada no logout);
- recuperação de senha (solicitação com e-mail existente/inexistente sempre com a mesma mensagem, envio do e-mail, token válido/expirado/inválido/não reutilizável, confirmação de senha, login com a nova senha, rate limit);
- headers de segurança/CSP presentes tanto em respostas normais quanto em respostas geradas por exceção (redirect de guest);
- agenda administrativa (listagem, filtros, busca por nome/e-mail/telefone, detalhe, cancelar/concluir/não comparecimento, regra temporal — só permitido depois que a consulta termina; cancelamento manual dispara e-mail, falha de envio não desfaz o cancelamento; payload `<script>` sempre escapado; busca com caracteres especiais de SQL tratada como texto literal);
- disponibilidade administrativa: alteração de horário/exceção/feriado/bloqueio sem consultas afetadas aplica direto; com consultas afetadas exige confirmação explícita e não aplica nada até confirmar (e não dispara e-mail); confirmação cancela as consultas, envia e-mail individual por consulta afetada (`Mail::fake()`), grava `AdminLog` e preserva o registro histórico (nunca apaga); falha de envio de uma notificação não impede o envio das demais nem desfaz nenhum cancelamento.

Rodar tudo:

```bash
docker compose exec laravel.test php artisan test
```

