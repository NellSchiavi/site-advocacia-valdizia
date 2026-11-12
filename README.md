# Site Dra. Valdizia Melo — Advocacia e Assessoria Jurídica

Site institucional com agendamento online de consultas e painel administrativo para o escritório da Dra. Valdizia Melo (Direito Previdenciário, Cível e de Família).

Acesse: https://dravaldiziamelo.com.br/

O sistema permite que o visitante conheça o escritório e agende uma consulta em horários realmente disponíveis, e que a advogada gerencie a agenda, o expediente, feriados e bloqueios pelo painel, sem alterar código.

## Funcionalidades

### Site público

- **Página institucional** com apresentação, áreas de atuação, diferenciais, chamada para agendamento e contato (WhatsApp, e-mail e Instagram).
- **Agendamento online** (`/agendamento`): assistente em 5 passos (Área → Data → Horário → Dados → Confirmação). Nome, e-mail, telefone e assunto são obrigatórios; a observação é opcional. Toda a validação é refeita no backend.
- **Página de confirmação** acessível por URL assinada e temporária (válida por 2 dias).
- **E-mail de confirmação** enviado ao cliente após o agendamento.
- **Política de Privacidade** (`/politica-de-privacidade`).
- **SEO básico**: title, description, canonical, Open Graph e `/sitemap.xml` gerado dinamicamente a partir de `APP_URL`.

### Painel administrativo (`/admin`)

- **Login** com e-mail e senha, limitação de tentativas e mensagem de erro genérica.
- **Recuperação de senha** por e-mail, com resposta idêntica exista ou não a conta informada.
- **Dashboard** com consultas do dia, confirmadas, futuras e áreas ativas.
- **Agenda**: visão diária e semanal, filtros por data/status/área, busca por nome, e-mail ou telefone, detalhe da consulta sob demanda e as ações **cancelar**, **concluir** e **marcar não comparecimento**. Concluir e não comparecimento só são permitidos depois do término da consulta. O cancelamento envia e-mail ao cliente.
- **Disponibilidade**: horários semanais, exceções de horário em datas específicas, feriados (anuais ou de um ano específico) e bloqueios de dia inteiro. Uma alteração que afete consultas já agendadas exige confirmação explícita, exibindo as consultas que serão canceladas; ao confirmar, a alteração e os cancelamentos ocorrem na mesma transação, os clientes são notificados por e-mail e a operação é registrada em log de auditoria (`admin_logs`).

## Tecnologias

- PHP 8.3 e Laravel 13
- MySQL 8.4
- Blade, Tailwind CSS 4 e Alpine.js 3
- Vite 8
- PHPUnit 12
- Docker / Laravel Sail (ambiente local, com Mailpit para captura de e-mails)

## Arquitetura e decisões técnicas

Aplicação monolítica renderizada no servidor (Blade), sem SPA. Os controllers apenas traduzem HTTP para os serviços de domínio em `app/Services`:

- **`AvailabilityService`** — única fonte de verdade da disponibilidade, usada pelo fluxo público, pela agenda e pelo painel de disponibilidade. Pipeline: expediente semanal → exceções da data → feriados → agendamentos existentes → horários livres. O frontend nunca decide o que está disponível.
- **`AppointmentService`** — criação de agendamentos e transições de status. Cada ação de status é uma rota explícita; o backend nunca aceita um campo `status` arbitrário.
- **`AdminAvailabilityService`** — orquestra alterações de disponibilidade: detecta consultas afetadas (via `AvailabilityService`), exige confirmação, cancela, registra auditoria e dispara as notificações.
- **`AppointmentNotificationService`** — único ponto de envio de e-mail transacional ao cliente.

**Concorrência de agendamento.** Cada data tem uma linha em `agenda_locks`, travada com `SELECT ... FOR UPDATE` dentro da transação. A disponibilidade é revalidada com o lock em mãos, e um índice único em `appointments` funciona como rede de segurança adicional. Alterações administrativas que afetam várias datas adquirem os locks em ordem cronológica, evitando deadlock com reservas públicas simultâneas.

**E-mails.** São enviados somente depois do commit da transação correspondente. Uma falha de envio é registrada em log (sem dados pessoais) e nunca desfaz a operação já persistida. Cada e-mail tem versão HTML e texto simples (`resources/views/emails/`). Eventos:

| Evento | Mailable | Origem |
|---|---|---|
| Confirmação de agendamento | `ConsultaConfirmadaMail` | `AppointmentController::store()` |
| Cancelamento manual | `ConsultaCanceladaMail` | `AdminAgendaController::cancelar()` |
| Cancelamento por alteração de disponibilidade | `ConsultaCanceladaMail` | `AdminAvailabilityService` |
| Redefinição de senha | `ResetPasswordMail` | `User::sendPasswordResetNotification()` |

**Nomenclatura das áreas.** Internamente (banco e painel) a área se chama "Direito Civil"; na comunicação pública é exibida como "Direito Cível", via `AreaAtuacao::nomePublico()`.

## Segurança

- **Headers de segurança e CSP** (`App\Http\Middleware\SecurityHeaders`), aplicados a todo o grupo `web`, inclusive a respostas geradas por exceções (`bootstrap/app.php`): `X-Content-Type-Options`, `Referrer-Policy`, `X-Frame-Options`, `Permissions-Policy` e `Content-Security-Policy` com `default-src 'self'`. Todo o front-end é servido pelo próprio domínio, sem CDN.
- **Limitação conhecida da CSP**: a build padrão do Alpine.js exige `'unsafe-eval'` em `script-src` (avaliação de expressões via `new Function()`) e `'unsafe-inline'` em `style-src` (`x-show`/`x-transition` alteram `element.style`). Remover essas concessões requer migrar para a build `@alpinejs/csp`.
- **Autenticação**: hash nativo do Laravel, limite de 5 tentativas por e-mail+IP além do `throttle` da rota, sessão regenerada no login e invalidada no logout. Todas as rotas do painel exigem `auth` e o Gate `access-admin-panel` (baseado em `users.role`).
- **Recuperação de senha** via `PasswordBroker` nativo (token com hash, expiração de 60 minutos, uso único).
- **Rate limiting**: `throttle:60,1` nos endpoints de disponibilidade, `throttle:10,1` no envio do agendamento e no login, `throttle:5,1` na recuperação de senha.
- **CSRF** em todos os formulários; **XSS**: nenhuma saída sem escape com dado de cliente; **SQL**: apenas Eloquent/query builder com bindings.
- **Minimização de dados**: a listagem da agenda não carrega e-mail, telefone ou observação dos clientes; esses dados só são lidos ao abrir o detalhe de uma consulta.

## Requisitos

- Docker Desktop (com Docker Compose v2) em execução
- Git

PHP, Composer e Node não precisam estar instalados na máquina: tudo roda dentro dos containers.

## Instalação

```bash
# 1. Clonar o repositório
git clone https://github.com/NellSchiavi/site-advocacia-valdizia.git
cd site-advocacia-valdizia

# 2. Criar o arquivo de ambiente
cp .env.example .env

# 3. Construir as imagens e subir os containers (aplicação, MySQL, Mailpit)
docker compose build
docker compose up -d

# 4. Instalar as dependências PHP
docker compose exec laravel.test composer install

# 5. Gerar a chave da aplicação
docker compose exec laravel.test php artisan key:generate

# 6. Criar o schema e popular com dados de desenvolvimento
docker compose exec laravel.test php artisan migrate --seed

# 7. Instalar as dependências JS e gerar os assets
docker compose exec laravel.test npm install
docker compose exec laravel.test npm run build
```

Endereços locais:

- Site: `http://localhost`
- Painel administrativo: `http://localhost/admin/login`
- Mailpit (e-mails capturados, sem envio real): `http://localhost:8025`

### Redes com inspeção TLS (proxy/antivírus)

Se a rede fizer inspeção HTTPS, o `docker compose build` pode falhar com erro de certificado. Nesse caso, exporte o certificado raiz da ferramenta de segurança em formato `.crt`, coloque-o em `docker/8.3/certs/` (pasta ignorada pelo Git) e execute o build novamente.

## Comandos úteis

| Ação | Comando |
|---|---|
| Iniciar / parar os containers | `docker compose up -d` / `docker compose down` |
| Logs da aplicação | `docker compose logs -f laravel.test` |
| Shell do container | `docker compose exec laravel.test bash` |
| Cliente MySQL | `docker compose exec mysql mysql -u sail -p valdizia_melo` |
| Rodar migrations | `docker compose exec laravel.test php artisan migrate` |
| Recriar o banco com seeders | `docker compose exec laravel.test php artisan migrate:fresh --seed` |
| Rodar os testes | `docker compose exec laravel.test php artisan test` |
| Rodar um arquivo de teste | `docker compose exec laravel.test php artisan test --filter=AdminAvailabilityTest` |
| Build dos assets | `docker compose exec laravel.test npm run build` |
| Vite em modo desenvolvimento | `docker compose exec laravel.test npm run dev` |

## Dados de desenvolvimento (seeders)

`php artisan db:seed` cria:

- **Administrador de desenvolvimento**: `dev@valdiziamelo.local` / `TrocarEssaSenha123!` — credencial exclusiva do ambiente local; nunca deve ser usada em homologação ou produção.
- Áreas de atuação: Direito Previdenciário, Direito Civil e Direito de Família.
- Expediente padrão: segunda a quinta 09:00–18:00, sexta 09:00–17:00; sábado e domingo sem atendimento.
- Feriados nacionais fixos (recorrência anual) e o feriado municipal de Ferraz de Vasconcelos (14/10). Feriados móveis (Carnaval, Sexta-feira Santa, Corpus Christi) devem ser cadastrados anualmente pelo painel.
- Configuração global: consultas de 55 minutos com 5 minutos de intervalo, antecedência mínima de 2 horas e máxima de 60 dias, confirmação automática. Duração e intervalo não são editáveis pelo painel.

## Variáveis de ambiente

A lista completa e comentada está em `.env.example`, que contém apenas valores de desenvolvimento local. Destaques:

- `APP_TIMEZONE=America/Sao_Paulo` e `APP_LOCALE=pt_BR`.
- `DB_*` — em desenvolvimento aponta para o serviço `mysql` do `compose.yaml`.
- `MAIL_*` — em desenvolvimento usa o Mailpit. Em produção, configure um provedor transacional (ex.: Resend) ou o SMTP do domínio; o código não muda.
- `NOTIFICATION_EMAIL` — e-mail de contato do escritório.
- `SESSION_SECURE_COOKIE` — deve ser `true` em produção (HTTPS).
- `QUEUE_CONNECTION` — os e-mails são enviados de forma síncrona após o commit; a tabela `jobs` já existe caso o envio passe a usar fila.

## Estrutura do projeto

```
app/
  Exceptions/          # exceções de domínio (horário indisponível, transição de status inválida,
                       # alteração de agenda que afeta consultas)
  Http/
    Controllers/       # AppointmentController (público) e Admin/* (painel)
    Middleware/        # SecurityHeaders
    Requests/          # validação dos formulários público e administrativo
  Mail/                # e-mails transacionais
  Models/              # User, AreaAtuacao, AvailabilityRule, AvailabilityException, Holiday,
                       # ConfiguracaoGlobal, Appointment, AgendaLock, AdminLog
  Services/            # regras de negócio (ver "Arquitetura")
database/
  migrations/          # schema versionado
  seeders/             # dados iniciais de desenvolvimento
docker/8.3/            # imagem do container da aplicação (baseada no runtime do Sail)
resources/
  css/app.css          # design tokens (paleta e tipografia) e componentes utilitários
  js/agendamento.js    # assistente de agendamento público (Alpine.js)
  js/agenda-admin.js   # modal de detalhe e ações da agenda administrativa
  views/pages/         # páginas públicas
  views/admin/         # telas do painel
  views/components/    # layout e seções reutilizáveis
  views/emails/        # e-mails (HTML e texto simples)
routes/web.php         # rotas públicas e do painel
tests/Feature/         # testes de integração
```

## Testes

```bash
docker compose exec laravel.test php artisan test
```

A suíte usa o banco MySQL de testes criado pelo Sail e cobre:

- resolução de disponibilidade (expediente, exceções, feriados, bloqueios, sobreposição, duração e intervalo);
- concorrência de agendamento (lock por data, com processos concorrentes reais via `pcntl_fork` quando disponível);
- fluxo público (validação, criação, URL assinada, rate limit, e-mail de confirmação, escape de conteúdo malicioso);
- autenticação, autorização, sessão e recuperação de senha do painel;
- headers de segurança e CSP, inclusive em respostas geradas por exceção;
- agenda administrativa (filtros, busca, detalhe, transições de status, e-mail de cancelamento, XSS e SQL injection);
- disponibilidade administrativa (confirmação de conflitos, rollback, cancelamento, auditoria e notificações).

## Produção

Checklist para o ambiente de produção:

- `APP_ENV=production` e `APP_DEBUG=false`.
- `APP_URL` com o domínio real em `https://`.
- `APP_KEY` própria, gerada no servidor; o `.env` de produção nunca é versionado.
- HTTPS com certificado válido e `SESSION_SECURE_COOKIE=true`.
- `DB_*` com as credenciais do banco de produção.
- `MAIL_*` apontando para um provedor de e-mail transacional.
- Permissão de escrita em `storage/` e `bootstrap/cache/`.
- Não executar o `AdminUserSeeder` em produção; criar o administrador com credenciais próprias.
