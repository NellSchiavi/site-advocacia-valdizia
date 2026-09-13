# Site Dra. Valdizia Melo — Advocacia e Assessoria Jurídica

Site institucional com sistema de agendamento de consultas para a Dra. Valdizia Melo (Direito Previdenciário, Cível e de Família).

> Planejamento técnico completo (arquitetura, schema, decisões e riscos) em [`PLANO-TECNICO.md`](./PLANO-TECNICO.md). Este README cobre apenas o dia a dia de desenvolvimento.

**Status atual**: Fase 3 concluída (homepage + fluxo público de agendamento) e correções pós-auditoria (Fase 3.2) aplicadas. **Ainda não há painel administrativo nem envio de e-mail** — ver "O que ainda não foi implementado" abaixo.

## Stack

- PHP 8.3
- Laravel 13
- MySQL 8.4
- Blade + Tailwind CSS 4 + Alpine.js
- Docker / Laravel Sail para desenvolvimento local
- Vite para build de assets front-end
- PHPUnit para testes automatizados

## O que já foi implementado

- **Homepage** institucional (Blade + Tailwind), com áreas de atuação, e link para a Política de Privacidade (conteúdo placeholder — ver seção abaixo).
- **Agendamento público** (`/agendamento`): wizard em 5 passos (Área → Data → Horário → Dados → Confirmação) construído com Alpine.js sobre os endpoints de disponibilidade.
- **Disponibilidade real**, calculada pelo `AvailabilityService` (única fonte de verdade, usada tanto pelos endpoints públicos quanto pela revalidação no momento de gravar o agendamento): expediente semanal, exceções pontuais, feriados, bloqueios parciais e agendamentos já existentes.
- **Proteção contra concorrência**: lock transacional por data (`agenda_locks` + `SELECT ... FOR UPDATE`) + revalidação da disponibilidade dentro da transação + índice único como rede de segurança adicional — impede que duas reservas simultâneas ocupem o mesmo horário (comprovado com conexões MySQL reais em `AppointmentConcurrencyTest`, incluindo um teste com processos concorrentes via `pcntl_fork` quando disponível).
- **Área de atuação inativa não pode ser agendada**: a regra (`exists:areas_atuacao,id,ativo,1`) é aplicada tanto no `AgendarConsultaRequest` (camada HTTP) quanto dentro do `AppointmentService::agendar()` (camada de serviço) — não depende só do frontend esconder a opção.
- **URL de confirmação assinada e temporária**: gerada com `URL::temporarySignedRoute()`, válida por **2 dias**, protegida pelo middleware `signed` do Laravel. Uma assinatura adulterada, expirada, ou gerada para outro agendamento é rejeitada com 403 — a página nunca expõe dados de um agendamento que não seja o assinado.
- **Tratamento explícito de falha de API no wizard** (`resources/js/agendamento.js`): resposta HTTP não-2xx (incluindo 429 de rate limit), erro de rede, timeout de conexão lenta e corpo de resposta que não é JSON válido nunca são interpretados como "não há horários disponíveis" — sempre aparece um estado de erro coerente com o design ("Não foi possível carregar a disponibilidade. Verifique sua conexão e tente novamente.") com botão "Tentar novamente", sem uso de `alert()` nativo.
- **Rate limiting**: `throttle:60,1` nos endpoints de consulta de disponibilidade/horários e `throttle:10,1` no envio do formulário de agendamento.
- **Política de Privacidade**: página publicada em `/politica-de-privacidade`, com conteúdo placeholder (texto genérico) — precisa ser revisado por um advogado antes de qualquer uso em produção.

## O que ainda não foi implementado

- Painel administrativo (agenda, bloqueios, feriados, áreas de atuação, configurações).
- Autenticação administrativa (login/logout, proteção de rotas de admin).
- Configuração de agenda pelo painel (hoje os dados de expediente/configuração global só existem via seeders/Tinker).
- Envio de e-mail real (confirmação para o cliente, notificação interna) — em desenvolvimento o Mailpit captura tudo localmente, mas não há nenhuma `Mailable`/notificação implementada ainda.
- Configuração de ambiente de produção/Locaweb (hospedagem, domínio, certificado, variáveis de produção).

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

A aplicação fica disponível em `http://localhost` e o Mailpit (captura de e-mails locais, sem envio real) em `http://localhost:8025`.

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
| Rodar só um arquivo de teste | `docker compose exec laravel.test php artisan test --filter=AppointmentServiceTest` |
| Build de produção dos assets (Vite) | `docker compose exec laravel.test npm run build` |
| Servidor de desenvolvimento do Vite (hot reload) | `docker compose exec laravel.test npm run dev` |
| Abrir o Tinker (console interativo) | `docker compose exec laravel.test php artisan tinker` |

## Seeders (dados de desenvolvimento)

O comando `php artisan db:seed` cria:

- **Usuária administradora de desenvolvimento**: `dev@valdiziamelo.local` / `TrocarEssaSenha123!`
  ⚠️ **Credencial de desenvolvimento apenas — nunca a senha real da cliente.** Deve ser trocada antes de qualquer uso fora do ambiente local/homologação. Sem efeito prático hoje, já que não há painel/login administrativo implementado ainda.
- 3 áreas de atuação (Direito Previdenciário, Direito de Família, Direito Civil), todas ativas.
- Expediente padrão: segunda a sexta, 09:00–18:00 (sábado, domingo e feriados nascem bloqueados por não terem regra).
- Feriados nacionais fixos (recorrência anual). Feriados móveis (Carnaval, Sexta-feira Santa, Corpus Christi) precisam ser cadastrados manualmente todo ano — não são chutados no seeder.
- Configuração global: consulta de 60 minutos, sem intervalo entre consultas, antecedência mínima de 2h e máxima de 60 dias, modo de confirmação automático.

Todos esses valores serão reconfiguráveis pelo futuro painel administrativo — por enquanto só são editáveis via seeder ou Tinker.

## Variáveis de ambiente

Ver `.env.example` para a lista completa e comentada. Nenhuma credencial real está commitada — apenas valores de desenvolvimento local (Sail/MySQL/Mailpit). Destaques:

- `APP_TIMEZONE=America/Sao_Paulo`, `APP_LOCALE=pt_BR` — timezone e locale da aplicação já configurados para o Brasil (`config/app.php` lê esses valores do `.env`, nunca hardcoded).
- `DB_*` — em desenvolvimento aponta para o serviço `mysql` do `compose.yaml`; em homologação/produção deve apontar para o MySQL real do ambiente via variáveis de ambiente do próprio host (nunca editar código para trocar de banco).
- `MAIL_*` — em desenvolvimento usa o Mailpit (não envia e-mail de verdade). Não há nenhum envio de e-mail implementado ainda no código (ver "O que ainda não foi implementado").
- `NOTIFICATION_EMAIL` — endereço reservado para a futura notificação interna de cada novo agendamento (ainda sem uso no código).

## Estrutura do projeto

```
app/
  Exceptions/
    HorarioIndisponivelException.php   # lançada quando um slot deixou de estar disponível
  Http/
    Controllers/AppointmentController.php   # fluxo público de agendamento (HTTP <-> serviços de domínio)
    Requests/AgendarConsultaRequest.php      # validação do formulário de agendamento (inclui área ativa)
  Models/
    User, AreaAtuacao, AvailabilityRule, AvailabilityException,
    Holiday, ConfiguracaoGlobal, Appointment, AgendaLock, AdminLog
  Services/
    AvailabilityService.php   # única fonte de verdade da disponibilidade (pipeline documentado no código)
    AppointmentService.php    # fluxo de criação/cancelamento de agendamento, com lock + checagem de sobreposição
database/
  migrations/    # schema versionado (ver PLANO-TECNICO.md seção 5 para o desenho completo)
  seeders/       # dados iniciais de desenvolvimento (ver seção "Seeders" acima)
docker/
  8.3/           # build context do container da aplicação (baseado no runtime oficial do Sail)
resources/
  views/pages/   # homepage, agendamento (wizard), confirmação, política de privacidade
  js/agendamento.js   # lógica do wizard de agendamento (Alpine.js) + tratamento de falha de API
tests/
  Concerns/SeedsAgendaFixtures.php   # fixtures compartilhadas pelos testes de agenda
  Feature/AvailabilityServiceTest.php
  Feature/AppointmentServiceTest.php
  Feature/AppointmentConcurrencyTest.php
  Feature/AgendamentoPublicoTest.php   # fluxo HTTP público: página, endpoints, submissão, confirmação assinada
PLANO-TECNICO.md   # arquitetura, decisões, riscos e especificação completa do projeto
```

Ainda não existem controllers, rotas ou views do painel administrativo — só o fluxo público (homepage + agendamento) está implementado.

## Testes automatizados

A suíte tem **42 testes** (97 assertions), cobrindo:

- horário comercial, sábado/domingo bloqueados, feriado bloqueado, data bloqueada manualmente;
- horário disponível vs. já ocupado;
- duração da consulta e intervalo entre consultas configuráveis (não hardcoded);
- sobreposição total (mesmo horário) e parcial (com duração diferente);
- consultas adjacentes permitidas (não sobrepõem);
- cancelamento libera o horário para novo agendamento;
- modo de confirmação automático vs. manual (`configuracoes_globais.modo_confirmacao`);
- concorrência: um lock transacional por data (`agenda_locks` + `SELECT ... FOR UPDATE`) impede que duas reservas simultâneas ocupem a mesma data até a primeira commitar — comprovado com duas conexões MySQL reais e, quando `pcntl_fork` está disponível, com processos concorrentes de verdade (`AppointmentConcurrencyTest`);
- área de atuação inativa é rejeitada tanto na validação do formulário quanto dentro do serviço de agendamento;
- data passada e horário fora da grade de horários são rejeitados;
- os endpoints de disponibilidade/horários retornam 422 para uma área de atuação inexistente;
- o envio do formulário de agendamento respeita o rate limit (`throttle:10,1`) e retorna 429 ao ser excedido;
- a URL de confirmação assinada: aceita uma assinatura válida, rejeita assinatura inválida, rejeita quando expirada (após os 2 dias de validade) e rejeita quando gerada para um agendamento diferente do solicitado.

Rodar tudo:

```bash
docker compose exec laravel.test php artisan test
```

## Próximos passos

- Painel administrativo (login, agenda, configurações, bloqueios, feriados).
- Integração de envio de e-mail real (confirmações e notificações internas).
- Revisão jurídica do conteúdo da Política de Privacidade (hoje é placeholder).
- Ambiente de produção (Locaweb) — domínio, certificado, variáveis de ambiente e deploy.
