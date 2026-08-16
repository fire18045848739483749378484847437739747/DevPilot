# Documentação do Sistema — Gerenciador de Processos/Serviços

> Documento gerado para repasse de contexto a outro assistente (CLAUDE).
> Idioma da aplicação: `pt_BR`. Ambiente: Windows + Laragon (PHP em Windows).

---

## 1. Visão Geral

Aplicação Laravel + Filament (painel admin) para **gerenciar processos/serviços de longa duração** no Windows: cadastrar, iniciar, parar, reiniciar, matar, monitorar (PID, CPU, memória, uptime, porta) e visualizar logs. Inclui um **agente de supervisão** que detecta quedas e faz reinício automático.

Principais usos práticos: rodar `php artisan serve`, servidores `php -S`, scripts PHP/node/python em background, etc.

---

## 2. Stack & Ambiente

| Item | Valor |
|------|-------|
| PHP | 8.3.16 (Laragon) — binário em `C:\laragon\bin\php\php-8.3.16-Win32-vs16-x64\php.exe` |
| Framework | Laravel ^13.17 (na prática Laravel 13.x) |
| Painel | Filament ^5.7 (`filament/filament`) |
| Livewire | via Filament |
| SO | Windows 11 / Laragon; PowerShell 5.1 (atenção: **não** é o PS 7) |
| Locale | `pt_BR` (traduções em `lang/pt_BR/services.php`, `enums.php`, etc.) |
| Banco | Configurado para **MySQL** (`DB_DATABASE=gerenciador_services_dev`, root, sem senha, host 127.0.0.1:3306). Migrations também funcionam em SQLite. |
| Painel | rota `/admin`, com login (`->login()` no `AdminPanelProvider`) |

O usuário do painel é criado localmente com `php artisan filament:user` — não há credencial padrão versionada.

---

## 3. Estrutura de Arquivos (relevante)

```
app/
  Console/Commands/ServiceAgentCommand.php   # comando artisan services:agent
  Enums/
    ServiceStatus.php        # Running|Stopped|Starting|Restarting|Error (HasLabel/HasColor/HasIcon)
    ServiceEnvironment.php   # Development|Staging|Production
    RestartPolicy.php        # Manual|OnFailure|Always
  Filament/
    Pages/Dashboard.php
    Resources/Services/
      ServiceResource.php
      Actions/ServiceActions.php   # start/stop/restart/kill/logs (Filament Actions)
      Pages/CreateService.php      # afterCreate() → start se start_on_create
      Pages/EditService.php
      Pages/ListServices.php
      Pages/ViewService.php        # header: ações; widget monitor (topo) + logs (rodapé)
      Schemas/ServiceSchema.php    # formulário (create/edit)
      Schemas/ServiceInfolist.php  # visualização (view)
      Tables/ServicesTable.php     # listagem
    Widgets/
      ServiceStatsWidget.php   # stats globais (total/rodando/erro/parado/agente) — polling 5s
      ServiceMonitorWidget.php # PID/CPU/mem/uptime/porta do registro — polling 3s
      ServiceLogsWidget.php   # logs do registro
  Models/
    Service.php      # tabela services
    ServiceLog.php   # tabela service_logs (TYPE_STDOUT/STDERR/SYSTEM)
    User.php
  Providers/Filament/AdminPanelProvider.php
  Services/
    ProcessManager.php  # NÚCLEO: launch/stop/kill/metrics/logs/porta (PowerShell)
    PowerShell.php      # runner de scripts (-EncodedCommand UTF-16LE), usado pelo ProcessManager e pelo AgentInstaller
    ServiceAgent.php    # supervisão: auto-start, reinício automático, métricas, ingestão de log, rotação
    AgentState.php      # heartbeat/pid do agente (arquivos em storage/app/services)
    AgentInstaller.php  # registro do agente no Windows (Agendador de Tarefas / NSSM)
    LogIngestor.php     # captura incremental de stdout/stderr para service_logs
    LogRotator.php      # truncamento de logs grandes e limpeza dos órfãos
    HealthChecker.php   # health check HTTP (status esperado)
    AlertManager.php    # notificações do painel (queda, limite de reinícios, CPU/memória, health)
    CompanionServiceFactory.php  # cria queue:work / schedule:work / pail de um projeto
    HostEnvironment.php     # limpeza do .env do Gerenciador para os processos filhos
    NetworkAddresses.php    # IPv4 da máquina, ordenados (rede real antes de adaptador virtual)
    CommandRewriter.php     # lê/troca o host dentro da linha de comando
    ProjectCommandCatalog.php  # catálogo fechado de comandos, filtrado pela pasta
    ProjectCommandRunner.php   # executa o comando na pasta do projeto (síncrono)
database/migrations/
  2026_08_14_000001_create_services_table.php
  2026_08_14_000002_create_service_logs_table.php
  2026_08_15_000001_add_monitoring_fields_to_services_table.php
  2026_08_15_000002_create_service_metrics_table.php
  2026_08_15_000003_add_log_ingestion_fields.php
  2026_08_15_000004_add_health_and_alert_fields_to_services_table.php
  2026_08_15_000005_create_service_groups_table.php
  2026_08_15_000006_create_notifications_table.php
lang/pt_BR/services.php, enums.php, panel.php, widgets.php, groups.php, agent.php, ...
routes/web.php, routes/console.php
```

---

## 4. Modelo de Dados

### `services`
Campos principais: `name`, `slug` (único, auto), `description`, `command` (texto completo, ex.: `php artisan serve --host=192.168.0.11 --port=8004`), `working_directory`, `user` (opcional), `environment`, `port` (opcional), `env_vars` (json array chave→valor), `auto_start_on_boot`, `auto_restart`, `restart_policy`, `max_restarts`, `stop_timeout`.
Campos de runtime/monitoramento: `status`, `pid`, `exit_code`, `started_at`, `cpu_usage`, `memory_kb`, `uptime_seconds`, `port_open`, `last_metrics_at`, `last_restart_at`, `restart_count`, `last_error`, `log_path_out`, `log_path_err`.

> **Nota importante:** o modelo **não** usa mais `#[Hidden(['command'])]`. Esse atributo foi removido porque o Filament preenche o formulário de edição a partir de `toArray()`, e o `command` (hidden) ficava vazio → falha de validação `required` ao salvar edição. Hoje o `command` é exposto normalmente (não aparece em listagem/infolist, então não há vazamento).

Campos acrescentados depois (ver seções 12–16): `service_group_id`, `boot_order`, `log_offset_out`, `log_offset_err`, `health_check_enabled`, `health_check_path`, `health_check_status`, `health_check_timeout`, `health_status`, `health_last_code`, `health_error`, `health_failures`, `health_checked_at`, `alerts_enabled`, `alert_cpu_threshold`, `alert_memory_threshold_mb`.

### `service_logs`
`service_id`, `type` (`stdout`|`stderr`|`system`), `level` (`debug`|`info`|`warning`|`error`|`critical`), `message`.

### `service_groups`
`name`, `slug`, `description`, `color`, `start_delay_seconds`. Um serviço pertence a no máximo um grupo.

### `notifications`
Tabela padrão do Laravel, usada pelo sino de notificações do Filament (alertas — ver seção 14).

### Enums
`ServiceStatus` tem `isActive()` = Running|Starting|Restarting. `isPending()` no Model = Starting|Restarting (importante: bloqueia `start()`).

---

## 5. Núcleo — `ProcessManager` (`app/Services/ProcessManager.php`)

Responsável por **executar processos no Windows via PowerShell**. Pontos críticos:

- **Lançamento:** gera um script PowerShell (`buildStartScript`) que faz `Start-Process -FilePath <exe> -ArgumentList @(...)` com `-WorkingDirectory`, redireciona stdout/stderr para arquivos de log, e captura o PID. Retorna `PID|<pid>` (sucesso) ou `EXITED|<code>|<msg>` (falha imediata) ou erro genérico.
- **Executável:** `resolveExecutable($token, $dir)`:
  - Se o token paracer caminho (contém `\`, `/`, ou termina com `.exe/.cmd/.bat/.ps1/.php`) → `File::exists` no token e em `$dir\$token`.
  - Caso contrário → `Get-Command` no PowerShell.
  - **Fallback para `php`/`php.exe`:** usa `PHP_BINARY` (que é exatamente o PHP do Laragon). Isso corrige "Executável não encontrado: php" quando o PHP não está no PATH do subprocesso PowerShell.
- **Parada:** `stop()` faz `Stop-Process`+`taskkill /T /F` com timeout (`stop_timeout`); `kill()` faz `taskkill /PID /T /F` imediato.
- **Métricas:** `metrics($pid)` usa `Get-Process` (CPU em segundos, WorkingSet64, StartTime) e calcula CPU% delta em `ServiceAgent::refreshMetrics`.
- **Porta:** `checkPort($port)` (netstat LISTENING) e **`killProcessOnPort($port, $onlyPhp=false)`** (mata processos escutando na porta via `netstat` + `taskkill`).
- **Logs:** `tailLog($record, $lines)` lê os arquivos `log_path_out`/`log_path_err`.

### Bug crítico já corrigido (portas/órfãos)
`php artisan serve` **não** roda o servidor diretamente: ele spawna um processo filho `php -S` (que é quem segura a porta). Ao **parar**, o pai era morto mas o filho `php -S` ficava "órfão" e continuava ocupando a porta → reiniciar falhava ao fazer bind (servidor "rodando" mas sem servir, ou erro confuso).

**Correção aplicada:**
- `stop()` e `kill()` agora chamam `killProcessOnPort($service->port)` para matar o filho órfão.
- `start()` limpa a porta antes de lançar (`killProcessOnPort($port, onlyPhp:true)`), garantindo que reiniciar funcione.
- Mensagem de falha agora é clara: `"A porta :port já está em uso por outro processo."` (lang `services.messages.port_in_use`) em vez de "Não foi possível iniciar o serviço."

> ⚠️ **Armadilha PowerShell:** a variável `$pid` é **somente-leitura** (PID do processo atual). O script de `killProcessOnPort` usa `$listenerPid` (não `$pid`) — usar `$pid` quebra silenciosamente a limpeza de porta.

---

## 6. Agente de Supervisão — `ServiceAgent` + `ServiceAgentCommand`

Comando: `php artisan services:agent` (loop infinito) ou `--once` (um ciclo) ou `--interval=N` (segundos).

`ServiceAgent::supervise()`:
1. Se `bootstrap`, `ensureAutoStart()` inicia serviços com `auto_start_on_boot`.
2. Para serviços Running/Starting/Restarting:
   - sem PID → `handleMissingPid` (status Error).
   - processo morto (`isAlive`) → `handleUnexpectedExit`.
   - vivo → `refreshMetrics` (CPU/mem/uptime/porta/heartbeat).
3. `AgentState::ping()` grava heartbeat em `storage/app/services/agent.heartbeat`.

`handleUnexpectedExit`:
- Se `auto_restart` e `restart_policy != Manual` e `restart_count < max_restarts` e último reinício há ≥5s → seta status `Stopped`, incrementa `restart_count`, `sleep(2)`, e `start($service, false)`. **Importante:** antes de `start()` ele seta o status para `Stopped` (senão `start()` é bloqueado por `isPending()`).
- Caso contrário → status `Error`.

`AgentState`: `isRunning($threshold=20)` considera o agente vivo se o heartbeat foi atualizado nos últimos 20s. Widget `ServiceStatsWidget` mostra o status do agente.

---

## 7. Interface Filament

- **Listagem** (`ListServices` + `ServicesTable`): colunas nome/status (badge)/PID/porta/etc., com ações em linha (start/stop/restart/kill/view/edit via `ServiceActions`).
- **Criar** (`CreateService`): `afterCreate()` lê `$this->data['start_on_create']` e inicia o serviço. O campo `start_on_create` usa `dehydrated(false)` (não vai para o banco) e por isso é lido de `$this->data`, não de `mutateFormDataBeforeCreate`.
- **Visualizar** (`ViewService`): infolist (`ServiceInfolist`) + widget `ServiceMonitorWidget` (topo) + `ServiceLogsWidget` (rodapé). Ações start/stop/restart/kill no header.
- **Editar** (`EditService`): reuso do `ServiceSchema`.
- **Formulário** (`ServiceSchema`): seções Identificação / Execução / Comportamento. `name` gera `slug` automático (`Str::slug` em `afterStateUpdated`). Campos: `command` (textarea), `working_directory`, `environment`, `user`, `port`, `env_vars` (KeyValue), toggles `auto_start_on_boot`/`auto_restart`, `restart_policy`, `max_restarts`, `stop_timeout`, `start_on_create`.
- **Infolist** (`ServiceInfolist`): `env_vars` usa `getStateUsing` retornando array formatado `CHAVE=valor` (TextEntry itera itens de array; usar `formatStateUsing` em array não funciona — ver gotchas Filament abaixo).
- **Widgets** usam trait `CanPoll` e sobrescrevem `getPollingInterval()` (não redeclaram a propriedade `$pollingInterval`, que conflita com o default do trait).

### Ações (`ServiceActions`)
`start/stop/restart/kill` chamam `ProcessManager` e disparam notificação + `$refresh`. `stop/restart/kill` usam `requiresConfirmation()`. `logs` abre modal com `tailLog`.

---

## 8. Gotchas de ambiente Windows / Filament v5 (importante p/ manutenção)

1. **Argumentos PowerShell:** `-ArgumentList` com string única/aspas estraga argumentos. Sempre usar **arrays** (`@('artisan','serve','--host=...','--port=...')`). Elementos com `[ \t"&|<>^()]` devem vir pré-citados (`psArg`).
2. **`php -r "sleep(15)"` SEM ponto-e-vírgula é PHP inválido** → erro de parse silencioso. Sempre terminar com `;`.
3. **Resolução de `php`:** o binário vem de `PHP_BINARY` (fallback em `resolveExecutable`), não do PATH do subprocesso.
4. **Portas/órfãos:** ver seção 5 (já corrigido). Ao mexer em `stop`/`start`, sempre considerar o filho `php -S` do `artisan serve`.
5. **Filament v5 API:**
   - `Set` fica em `Filament\Schemas\Components\Utilities\Set` (não `Filament\Forms\Set`).
   - `BadgeColumn::enum()` **não existe**; enums com `HasLabel` já viram label/badge automaticamente na coluna. Evitar `->enum()`.
   - `TextEntry` com array: use `getStateUsing(fn (Model $r): array => ...)` + `listWithLineBreaks()`. `formatStateUsing` é chamado por **item** quando o estado é array — não use para formatar o array inteiro.
   - `->schema()` ainda existe em Actions.
   - Widgets: sobrescrever `getPollingInterval()`, não declarar `$pollingInterval`.
   - Imports de enums nos arquivos Filament: `use BackedEnum;` (senão resolve para `App\...\BackedEnum` inexistente).
6. **`$pid` é read-only no PowerShell** (seção 5).
7. **BOM UTF-8:** `Set-Content -Encoding UTF8` no PowerShell 5.1 escreve BOM e corrompe arquivos PHP. Evitar; editar via ferramentas que não adicionem BOM.
8. **Testes Livewire de formulário:** `set('data.field')`/`fillForm()` em `Livewire::test` não hidrata componentes do schema de forma confiável para `call('save')` (validação pode falhar de forma silenciosa). Validar fluxos de save no navegador ou usar os métodos do `ProcessManager` diretamente.

---

## 9. Como rodar / setup

```bash
composer install
cp .env.example .env   # ajustar DB (MySQL ou SQLite)
php artisan key:generate
php artisan migrate --force
php artisan filament:user   # criar admin
php artisan serve           # app em :8000 (ou outro porte)
```

Agente de supervisão (em produção / segundo plano):
```bash
php artisan services:agent          # loop infinito
php artisan services:agent --once   # um ciclo (útil p/ agendador/task)
php artisan services:agent --interval=5
```

Para não depender de um terminal aberto, registre o agente no Windows (**terminal como Administrador** — ver seção 12):
```bash
php artisan agent:install
```

---

## 10. Exemplo de cadastro (servindo um projeto Laravel)

- **Nome:** `meu-projeto - Web`
- **Comando:** `php artisan serve --host=192.168.0.11 --port=8004`
- **Diretório de execução:** `C:\laragon\www\Sistema_FazendaGestor\meu-projeto`
- **Porta:** `8004`
- **Ambiente:** `Development`
- **Iniciar ao criar:** marcado
- O `php` é resolvido automaticamente para o binário do Laragon; não precisa digitar o caminho.

---

## 10.1. Vazamento de variáveis de ambiente (corrigido — importante)

O Laravel do **próprio Gerenciador** exporta o seu `.env` para o ambiente do processo (via `putenv` do Dotenv). Como o `Start-Process` do PowerShell herda o ambiente do pai, todo serviço filho herdava `DB_DATABASE`, `APP_KEY`, `CACHE_STORE` etc. do Gerenciador — e o Dotenv **não sobrescreve** variáveis já presentes no ambiente, então o `.env` do projeto filho era ignorado.

Sintoma real: o meu-projeto tentava ler a tabela `planos` no banco `gerenciador_services_dev` em vez de `meu-projeto`.

**Correção:** `ProcessManager::start()` monta um array de ambiente que remove (valor `false`, suportado nativamente pelo `Symfony\Process`) todas as chaves lidas do `.env` do Gerenciador — `hostEnvKeysToClear()` — e mescla os `env_vars` do serviço. O ambiente é controlado **do lado do PHP**, nunca via `Remove-Item Env:\...` dentro do script PowerShell: esse padrão é bloqueado silenciosamente pelo Defender/AMSI quando enviado por `-EncodedCommand` (stdout e stderr vazios, exit 1).

> Efeito colateral positivo: o campo **Variáveis de Ambiente** (`env_vars`), que existia na UI mas nunca era aplicado, passou a funcionar.

---

## 10.2. Monitoramento sem o agente (corrigido)

Antes, `cpu_usage`/`memory_kb`/`port_open` só eram atualizados por `php artisan services:agent`. Sem o agente rodando, o painel mostrava tudo `—` e "Porta sem resposta" mesmo com o processo no ar.

**Correção:** `App\Services\MetricsCollector` é a fonte única de verdade, usada tanto pelo agente quanto pelo painel:

- `ProcessManager::probe(array $pids)` faz **uma única** chamada ao PowerShell que devolve CPU/memória de todos os PIDs **e** a lista de portas em escuta (antes eram 2 processos do PowerShell por serviço, o que inviabilizava o polling).
- `cpu_seconds_total` é **persistido** na tabela `services` — o cálculo de CPU% por delta funciona mesmo com cada coleta acontecendo em um processo PHP diferente (requisições web).
- `MetricsCollector::THROTTLE_SECONDS` evita disparar PowerShell a cada polling de widget.
- `ServiceMonitorWidget` e `ListServices` coletam sob demanda; o agente continua sendo necessário apenas para **reinício automático** e `auto_start_on_boot`.

Histórico para os gráficos fica em `service_metrics` (amostra a cada 15s, retenção de 24h).

---

## 10.3. Acesso ao painel e `APP_ENV`

O modelo `User` **não** implementa `FilamentUser`. Nesse caso o Filament só libera o painel quando `config('app.env') === 'local'` — com qualquer outro `APP_ENV` o painel inteiro responde **403**. Se um dia for publicar, implemente `FilamentUser::canAccessPanel()` no `User`.

---

## 11. Estado atual (contexto de desenvolvimento)

- Todas as telas (Dashboard, List, Create, View, Edit) renderizam sem erro.
- Start/stop/restart/kill validados ponta a ponta (PID real, métricas, reinício automático do agente).
- Edição + save validado (correção do `#[Hidden]`).
- Launch de `php artisan serve` validado (incluindo limpeza de porta/órfãos).
- Vazamento de `.env` para processos filhos corrigido (seção 10.1).
- Monitoramento funciona sem o agente (seção 10.2).
- `tests/Feature/PanelSmokeTest.php` renderiza todas as telas + gráficos e checa o tema/sidebar no HTML autenticado — é a rede de segurança para uso incorreto da API do Filament, que só falha em tempo de renderização.

### Recursos adicionados nesta rodada
- **Link de acesso** por serviço (`accessUrl()`): host e porta derivados do comando (`--host=`, `--port=`, `-S host:porta`), com `url_scheme` e `url_path` configuráveis. Ação "Abrir no navegador" na listagem e no cabeçalho.
- **Limpar logs** (`ProcessManager::clearLogs`): trunca os arquivos (não apaga — o processo mantém o handle aberto) e limpa `service_logs`.
- **Seletor de pastas** (`DirectoryBrowser`): navega pelas pastas do servidor, restrito às raízes de `config/services_manager.php` (`dev_roots`, via env `DEV_ROOTS`).
- **Sugestão de porta livre** (`PortAllocator`) dentro da faixa configurada.
- **Dashboard**: gráfico de CPU/memória com filtro de período, rosca de status e **mapa de portas** (livre / serviço no ar / serviço parado / processo externo).
- **Tema hacker**: `public/css/hacker-theme.css` injetado por `renderHook` — sem build de Vite/npm e sem `filament:assets`; editar o arquivo já reflete (cache-bust por `filemtime`).
- **PT-BR**: `validation.php`, `auth.php`, `passwords.php`, `pagination.php` e uso das chaves de `services.resource.*` no `ServiceResource`.
- **Operações em lote** (`ServiceOrchestrator`): botões "Iniciar/Parar/Reiniciar todos" no cabeçalho do Dashboard, com confirmação e notificação consolidada (`ok`/`falhas`/`ignorados`). **Serviços de `Production` são sempre excluídos** — derrubar produção a partir de um botão "parar todos" seria caro demais. Coberto por `tests/Feature/ServiceOrchestratorTest.php`.
- **Controle rápido** (`ServiceControlWidget`): tabela no Dashboard com start/stop/restart/abrir como botões de ícone direto na linha, e kill/logs/limpar logs no menu.
- **Landing page pública** (`resources/views/welcome.blade.php`): CSS próprio inline (sem npm), responsiva, com instalação, agente, requisitos e licença. Metadados em `config/services_manager.php` → `project` (nome, repositório, autor, versão) via env.
- **Open source**: `LICENSE` (MIT) e `README.md`.

> ⚠️ O `LICENSE` está com o placeholder `<SEU NOME OU ORGANIZACAO>` — preencha antes de publicar.

---

## 12. Agente como serviço do Windows

Enquanto o agente dependia de `php artisan services:agent` em um terminal aberto, fechar o terminal derrubava o reinício automático e o `auto_start_on_boot` — e é exatamente por isso que as métricas apareciam "mortas".

`App\Services\AgentInstaller` registra o agente de dois jeitos:

| Método | O que faz | Observações |
|--------|-----------|-------------|
| `task` (padrão) | `Register-ScheduledTask` no Agendador de Tarefas | Nativo, sem dependência externa |
| `nssm` | Serviço real do Windows via NSSM | Exige `nssm` no PATH (ou `NSSM_PATH`) |

Gatilhos da tarefa:

- `--trigger=logon` (padrão): principal **S4U** com o usuário atual. Sem senha armazenada e **sem janela de console**; os serviços filhos continuam rodando com a sua conta, como hoje.
- `--trigger=boot`: principal **SYSTEM** com `-AtStartup`. Sobe antes de qualquer login, mas os processos filhos passam a rodar como SYSTEM.

A tarefa é registrada com `-ExecutionTimeLimit ([TimeSpan]::Zero)` (sem limite — o agente é um loop infinito), `-RestartCount 3` e `-MultipleInstances IgnoreNew`.

```bash
php artisan agent:install --method=task --trigger=logon
php artisan agent:status
php artisan agent:control start
php artisan agent:uninstall
```

Tudo isso **exige terminal elevado**. Como o painel roda sob o servidor web (normalmente sem elevação), `AgentInstaller::install()` detecta a falta de privilégio e devolve o comando pronto para colar — a página **Agente** (`App\Filament\Pages\AgentPage`) mostra esse comando em destaque, além do estado do registro, do heartbeat e do que exatamente será registrado (binário do PHP, diretório, comando).

> ⚠️ `AgentInstaller::status()` dispara três chamadas ao PowerShell (elevação + tarefa + serviço). A página não faz polling por causa disso; há um botão "Atualizar".

---

## 13. Captura de logs no banco e rotação

### 13.1. Ingestão (`LogIngestor`)

O processo filho é desacoplado do terminal (`Start-Process` com redirecionamento), então não há pipe para ler. A captura é **incremental por offset**: `services.log_offset_out` / `log_offset_err` guardam até que byte cada arquivo já foi lido, e cada passagem grava só o trecho novo em `service_logs`.

Detalhes que importam:

- A leitura para na **última quebra de linha**; uma linha pela metade fica para a próxima passagem (senão o log viraria fragmentos).
- Arquivo **menor** que o offset = truncado por baixo dos panos → recomeça do zero. Quem trunca de propósito (`LogRotator` e "limpar logs") **também zera o offset** — a detecção por tamanho é só o cinto de segurança.
- Teto de bytes por ciclo (`logs.max_bytes_per_cycle`, 256 KB) para um serviço tagarela não travar o painel.
- Retenção por serviço (`logs.max_rows_per_service`, 5000 linhas).
- É só I/O de arquivo, **sem PowerShell** — por isso roda também no `ListServices` e no widget de histórico, e não só no agente.

### 13.2. Nível do log

`service_logs.type` diz de onde a linha veio (stdout/stderr/system); `service_logs.level` diz a gravidade. São coisas diferentes e confundi-las dá ruído: **o `php -S` escreve o log de acesso inteiro — inclusive respostas 200 — no stderr**. Por isso `detectLevel()` reconhece primeiro o formato de acesso (`[200]:`, `[404]:`, `[500]:`) e classifica pelo status HTTP; só depois cai nas palavras-chave (`FATAL`, `ERROR`, `WARNING`, `DEBUG`).

### 13.3. Rotação (`LogRotator`)

- Arquivo do serviço acima de `logs.rotate_size_mb` (10 MB) é **truncado no lugar** após a ingestão — nunca renomeado nem apagado, porque o processo filho mantém o handle aberto.
- Arquivos que não são mais o log corrente de nenhum serviço e passaram de `logs.retention_days` (7 dias) são apagados.
- O agente chama `rotateIfDue()` a cada ciclo, com marcador de tempo em `storage/app/services/logs.rotated` (intervalo `logs.rotate_interval_minutes`). Manualmente: `php artisan services:logs-rotate --force`.

### 13.4. No painel

`ServiceLogTableWidget` (rodapé do View) é uma tabela sobre `service_logs` com **busca por texto** e **filtro por nível e origem**. O `ServiceLogsWidget` (tail do arquivo) continua existindo como console ao vivo.

> ⚠️ `TableWidget::makeTable()` sobrescreve o `heading()` definido em `table()` com um título derivado do nome da classe. Para o título valer, sobrescreva `getTableHeading()`.

---

## 14. Health check HTTP e alertas

### 14.1. Health check (`HealthChecker`)

Porta aberta não significa aplicação respondendo: dá para fazer bind e devolver 500 em toda requisição. Por serviço: `health_check_enabled`, `health_check_path`, `health_check_status` (esperado, padrão 200) e `health_check_timeout`.

Estados (`App\Enums\HealthStatus`): `unknown` → `ok` / `degraded` / `failing`. `degraded` é a fase intermediária: já falhou, mas ainda não bateu `health.failure_threshold` (3) falhas consecutivas. O alerta e o log só saem na **transição** para `failing`, e a recuperação registra e libera o cooldown.

O check tem intervalo próprio (`health.interval`, 30s) e é disparado de dentro do `MetricsCollector` — logo funciona com ou sem o agente. A ação "Verificar saúde" no painel força um check imediato.

### 14.2. Alertas (`AlertManager`)

Notificações de banco do Filament (sino do painel; `->databaseNotifications()` no `AdminPanelProvider`, tabela `notifications`). Eventos:

| Evento | Origem |
|--------|--------|
| Serviço caiu | `ServiceAgent::handleUnexpectedExit` / `handleMissingPid` |
| Limite de reinícios estourado | `ServiceAgent` (evento distinto de uma queda isolada) |
| CPU/memória acima do limite | `MetricsCollector::evaluateThresholds` |
| Health check falhando/normalizado | `HealthChecker` |

Cada alerta tem **cooldown por serviço** (`alerts.cooldown_minutes`, 10) via `Cache::add` — sem isso um serviço em loop de reinício viraria centenas de notificações idênticas. Os limites de CPU/memória são por serviço (`alert_cpu_threshold`, `alert_memory_threshold_mb`); nulos desligam a checagem daquele recurso.

> ⚠️ O envio é `try/catch` mudo de propósito: alerta é efeito colateral e não pode derrubar o ciclo de supervisão. O botão "Ver serviço" também é opcional — `ServiceResource::getUrl()` depende de um painel corrente, que não existe quando o agente roda no CLI.

---

## 15. Grupos de serviços (stacks)

`ServiceGroup` + `services.service_group_id` / `services.boot_order`. Subir o grupo percorre `boot_order` crescente com `start_delay_seconds` de pausa entre um serviço e o próximo (o dependente precisa que a dependência já esteja de pé); parar usa a **ordem inversa**. Implementado em `ServiceOrchestrator::startGroup/stopGroup/restartGroup`.

O `ensureAutoStart()` do agente também passou a respeitar `boot_order` e o delay do grupo.

Relação é **um-para-muitos** (um serviço pertence a no máximo um grupo). É o suficiente para o caso real — app + queue + vite do mesmo projeto — e evita a complexidade de pivot com ordem por grupo.

---

## 16. Detecção de projeto e atalhos

- `DirectoryBrowser::detect()` reconhece **Laravel** (existe `artisan`) e **Node** (`package.json` com script `dev`), devolvendo comando e nome sugeridos. No formulário, escolher a pasta pré-preenche comando, porta (via `PortAllocator`) e nome — **só o que estiver vazio**, nunca sobrescrevendo o que o usuário digitou.
- `CompanionServiceFactory` cria os serviços de apoio de um projeto Laravel (`queue:work`, `schedule:work`, `pail`) na mesma pasta, com o mesmo ambiente e `env_vars`, logo depois do serviço de origem na ordem de subida. Se a origem não tem grupo, cria um com o nome dela e coloca os dois lá — stack só faz sentido junto. Não duplica: mesmo comando na mesma pasta é o mesmo serviço.

---

## 17. Seleção de host (localhost / IP da máquina)

O host **não é coluna**: ele mora dentro do `command` (`--host=`, `-S host:porta`), que segue sendo a fonte única de verdade. O campo "Host de escuta" do formulário é `dehydrated(false)` e só **lê e reescreve** essa parte da linha (`App\Services\CommandRewriter`).

- `extractHost()` devolve `0.0.0.0` como está — diferente de `Service::resolvedHost()`, que mapeia para `127.0.0.1` na hora de montar o link clicável. O formulário precisa mostrar o que o usuário escolheu; o link precisa de um endereço acessível.
- `withHost()` troca o host existente, acrescenta `--host=` em um `artisan serve` que não tem, e **devolve o comando intacto** em qualquer outro caso — não dá para adivinhar a sintaxe de host de um script arbitrário.
- O seletor só aparece quando `supportsHost()` é verdadeiro (por isso o `command` é `live(onBlur: true)`); em um `queue:work` a escolha seria mentira.

`App\Services\NetworkAddresses` lista os IPv4 da máquina via `Get-NetIPAddress`, com fallback em `gethostbynamel()`. A ordenação importa: uma máquina de desenvolvimento tem muito mais IP do que o útil (WSL, VirtualBox, VPN, Topaz/loopback, link-local). A pontuação empurra para o fim os adaptadores virtuais (por nome), as faixas não-privadas e os endereços que não vieram de DHCP; e descarta `127.*`, `169.254.*` e `0.*`.

> ⚠️ `Get-NetIPAddress` custa **mais de 1 segundo** nesta máquina, e o Select reavalia as opções a cada render do Livewire. Por isso há cache de 5 min **e** um memo estático por processo. `forget()` limpa os dois (é o que o botão "Detectar IP da máquina" faz antes de reconsultar).

Padrão configurável em `services_manager.default_host` (env `SERVICE_DEFAULT_HOST`), usado ao sugerir o comando de um projeto detectado.

---

## 18. Comandos prontos do projeto

Página **Comandos** (`App\Filament\Pages\ProjectCommandsPage`): roda comandos de uma tacada só na pasta do projeto — o que roda em loop é serviço, não comando.

- **Catálogo fechado** (`services_manager.commands.catalog` + `App\Services\ProjectCommandCatalog`). O painel executa apenas o que está no config; não há campo de linha de comando livre. Rótulos e descrições em `lang/pt_BR/commands.php`, sobrescrevíveis por `label`/`description` no config.
- **Filtragem por capacidade da pasta**: `laravel` (tem `artisan`), `composer` (`composer.json`), `node` (`package.json`), `filament` (`vendor/filament` ou o pacote no `composer.json`). Um projeto sem Filament não vê `filament:upgrade`.
- **Destrutivos escondidos por padrão** (`migrate:fresh --seed`, `migrate:rollback`, `db:seed`, `queue:flush`): só aparecem depois do botão "Mostrar destrutivos", e ainda pedem confirmação via `wire:confirm`.
- Grupos: Diagnóstico, Cache, Banco de dados, Filas, Filament, Dependências e build.

`App\Services\ProjectCommandRunner` executa **sincronamente** (Symfony Process) e devolve a saída para a tela. Pontos críticos:

1. **Ambiente limpo.** Usa `HostEnvironment::forChild()` — a mesma limpeza do `ProcessManager::start()`, agora extraída para ser compartilhada. Sem ela, um `php artisan migrate` na pasta do projeto rodaria contra o banco do **Gerenciador** (é exatamente o bug da seção 10.1).
2. **Pasta restrita a `DEV_ROOTS`.** O runner é acionado pela web; sem essa checagem o painel viraria um shell no disco inteiro.
3. **Wrappers do Windows.** `composer` e `npm` são `.bat`/`.cmd`, que o `CreateProcess` não executa direto — vão por `cmd.exe /c`. `ProcessManager::resolveExecutable()` e `isCmdWrapper()` viraram públicos para o runner reusar (inclusive o fallback de `php` para `PHP_BINARY`).
4. **Timeout por comando** (`timeout` no item; 900 s para `composer install`/`npm install`, 300 s padrão).
5. **Rastro nos logs.** A execução é registrada em `service_logs` de todos os serviços daquela pasta — quem for investigar depois entende por que o comportamento mudou.

Atalho: a ação "Comandos do projeto" no serviço abre a página já apontada para a pasta dele (`?directory=`).

---

## 19. Testes

`php artisan test` — 55 testes. Além do `PanelSmokeTest` (que cobre grupos, página do agente, página de comandos, widget de histórico e o modal de atalhos):

- `LogIngestorTest` — leitura incremental, linha pela metade, arquivo encolhido, nível pelo status HTTP, rotação.
- `ServiceGroupTest` — ordem de subida/descida do stack e criação de companheiros.
- `HealthCheckTest` — porta aberta respondendo 500 não é "saudável", limiar de falhas, intervalo, alerta único.
- `AlertTest` — cooldown, desligamento por serviço, limites de CPU/memória.
- `HostSelectionTest` — troca de host nas três sintaxes, comando desconhecido intacto, ranking de IPs (virtual e link-local perdem para a rede real).
- `ProjectCommandTest` — capacidades da pasta, filtro de destrutivos, recusa fora de `DEV_ROOTS`, e uma **execução real** (`php artisan about`) provando que o runner resolve o PHP, roda na pasta certa e captura a saída.

> ⚠️ **Factory precisa espelhar os defaults do banco.** `Model::create()` devolve o model apenas com o que foi atribuído — colunas com default no banco ficam **nulas** no objeto em memória. Foi assim que um teste pegou `alerts_enabled` nulo silenciando todos os alertas logo após o cadastro.
