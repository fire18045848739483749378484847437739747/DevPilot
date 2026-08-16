# DevPilot

Painel web para iniciar, parar e monitorar processos de desenvolvimento no Windows.

Feito para quem roda vários projetos Laravel ao mesmo tempo e se perde entre terminais abertos, portas ocupadas e processos órfãos.

![Licença](https://img.shields.io/badge/licen%C3%A7a-MIT-22c55e)
![PHP](https://img.shields.io/badge/PHP-8.3%2B-777bb4)
![Laravel](https://img.shields.io/badge/Laravel-13.x-ff2d20)
![Filament](https://img.shields.io/badge/Filament-5.x-fdae4b)
![Windows](https://img.shields.io/badge/Windows-10%2F11-0078d4)

---

## Índice

- [Recursos](#recursos)
- [Requisitos](#requisitos)
- [Instalação](#instalação)
- [Agente de supervisão](#agente-de-supervisão)
- [Cadastrando um serviço](#cadastrando-um-serviço)
- [Grupos de serviços](#grupos-de-serviços)
- [Health check e alertas](#health-check-e-alertas)
- [Logs](#logs)
- [Comandos do projeto](#comandos-do-projeto)
- [Configuração](#configuração)
- [Arquitetura](#arquitetura)
- [Testes](#testes)
- [Segurança](#segurança)
- [Licença](#licença)

---

## Recursos

**Controle e monitoramento**

- **Controle pelo navegador** — iniciar, parar, reiniciar e matar processos, individualmente, por grupo ou todos de uma vez.
- **Monitoramento ao vivo** — PID, CPU, memória, uptime e estado da porta, com histórico em gráficos. Funciona **sem** o agente rodando: o painel coleta sob demanda, em uma única sondagem do PowerShell.
- **Mapa de portas** — quais portas estão livres, ocupadas por um serviço ou por um processo externo à ferramenta.
- **Link de acesso** — host e porta deduzidos do comando, com protocolo e caminho inicial configuráveis.

**Confiabilidade**

- **Agente como serviço do Windows** — registre com um comando (Agendador de Tarefas ou NSSM) e o monitoramento deixa de depender de um terminal aberto.
- **Reinício automático** — o agente detecta quedas e sobe o processo de novo, respeitando política e limite de tentativas.
- **Health check HTTP** — porta aberta não significa aplicação de pé; o DevPilot bate na URL e compara o status esperado.
- **Alertas no painel** — notificação quando um serviço cai, estoura o limite de reinícios, excede CPU/memória ou falha no health check.

**Produtividade**

- **Grupos de serviços** — suba ou derrube um stack inteiro (app + fila + agendador + Vite) com um clique, na ordem de dependência certa.
- **Detecção de projeto** — escolha a pasta e o DevPilot reconhece Laravel ou Node, sugerindo o comando, o nome e uma porta livre.
- **Atalhos do projeto** — crie `queue:work`, `schedule:work` e `pail` de um projeto em dois cliques, já agrupados.
- **Comandos prontos** — rode `migrate`, `optimize:clear`, `filament:upgrade`, `npm run build` e outros na pasta do projeto, direto do painel.
- **Seletor de host** — alterne entre `localhost`, o IP da rede local (detectado automaticamente) e `0.0.0.0`.
- **Seletor de pastas** — navegue pelos projetos do servidor em vez de digitar caminhos, restrito às raízes que você definir.

**Logs**

- **Console ao vivo** — stdout e stderr no navegador, com atualização contínua.
- **Histórico pesquisável** — cada linha vai para o banco com nível (`debug`/`info`/`warning`/`error`/`critical`), com busca e filtro.
- **Rotação automática** — arquivos grandes são truncados e os órfãos antigos, apagados. Nada de pasta de logs crescendo para sempre.

**Sem armadilhas**

- **Sem processos órfãos** — ao parar, o filho `php -S` do `artisan serve` também morre; a porta fica realmente livre.
- **Ambiente isolado** — cada serviço usa o próprio `.env`; as variáveis do DevPilot não vazam para os processos filhos.

## Requisitos

| Componente | Versão | Observação |
|---|---|---|
| PHP | 8.3+ | com a extensão `pdo` |
| Laravel | 13.x | — |
| Filament | 5.x | painel administrativo |
| Banco | MySQL 8 ou SQLite | ambos suportados |
| Sistema | Windows 10/11 | usa PowerShell para controlar processos |

> [!NOTE]
> O controle de processos é feito por PowerShell (`Start-Process`, `Get-Process`, `netstat`, `taskkill`). O DevPilot é **específico para Windows** — não roda em Linux ou macOS.

## Instalação

```bash
git clone https://github.com/EudesSA/DevPilot.git
cd DevPilot
composer install
```

```bash
cp .env.example .env
php artisan key:generate
```

Ajuste as credenciais do banco no `.env`. As demais chaves do DevPilot já vêm comentadas no `.env.example` — a mais importante é a raiz navegável pelo seletor de pastas:

```dotenv
DEV_ROOTS=C:\laragon\www
```

```bash
php artisan migrate
php artisan make:filament-user
php artisan serve
```

O painel fica em `/admin`.

> [!IMPORTANT]
> O modelo `User` não implementa `FilamentUser`. Nesse caso o Filament só libera o painel quando `APP_ENV=local` — com qualquer outro valor o painel inteiro responde **403**. Para publicar em outro ambiente, implemente `canAccessPanel()` no seu modelo de usuário.

## Agente de supervisão

O painel coleta métricas, captura logs e roda health checks sozinho. O agente é necessário para o que só funciona em segundo plano: **reinício automático**, **iniciar serviços no boot** e **manter tudo isso acontecendo com o navegador fechado**.

### Registrar no Windows (recomendado)

Enquanto o agente depender de um terminal aberto, ele morre quando você fecha a janela — e o reinício automático morre junto. Registre-o de vez:

```bash
php artisan agent:install
```

O comando precisa de um terminal **como administrador**. Se rodar sem elevação, ele mostra exatamente qual linha executar no terminal elevado.

| Opção | Padrão | Efeito |
|---|---|---|
| `--method=task` | ✅ | Agendador de Tarefas do Windows. Nativo, sem dependência externa. |
| `--method=nssm` | | Serviço do Windows via [NSSM](https://nssm.cc/). Exige o `nssm.exe` no PATH ou em `NSSM_PATH`. |
| `--trigger=logon` | ✅ | Sobe no seu logon, com a sua conta (principal S4U). Sem senha armazenada e sem janela de console. |
| `--trigger=boot` | | Sobe antes de qualquer login, como `SYSTEM`. Os serviços filhos passam a rodar como `SYSTEM` também. |

Comandos relacionados:

```bash
php artisan agent:status      # registro no Windows + heartbeat
php artisan agent:control start
php artisan agent:control stop
php artisan agent:uninstall
```

Tudo isso também está na página **Agente** do painel, que mostra o estado do registro, o último heartbeat e — quando o painel não está elevado — o comando pronto para copiar.

### Rodar manualmente

```bash
php artisan services:agent               # loop contínuo
php artisan services:agent --once        # um único ciclo
php artisan services:agent --interval=5  # intervalo em segundos
```

## Cadastrando um serviço

| Campo | Exemplo |
|---|---|
| Nome | `DEV_meu-projeto` |
| Diretório de execução | `C:\laragon\www\meu-projeto` |
| Comando | `php artisan serve --host=127.0.0.1 --port=8000` |
| Host de escuta | `127.0.0.1`, o IP da rede local ou `0.0.0.0` |
| Porta | `8000` |
| Caminho inicial | `admin` (opcional — compõe o link de acesso) |

Escolha primeiro a **pasta**: se for um projeto Laravel ou Node, o comando, o nome e uma porta livre são preenchidos sozinhos.

O binário do `php` é resolvido a partir de `PHP_BINARY`, então não é preciso informar o caminho completo.

### Host: localhost ou rede local

O campo **Host de escuta** lista `127.0.0.1`, os IPs reais da máquina e `0.0.0.0`. Escolher uma opção reescreve o host dentro do comando — funciona com `--host=`, `--host ` e `-S host:porta`.

A detecção ordena os IPs para o da rede de verdade aparecer primeiro: adaptadores virtuais (WSL, VirtualBox, VPN), faixas não-privadas e endereços de link-local (`169.254.*`) ficam por último ou são descartados.

Use o IP da rede local para abrir o projeto do celular; use `127.0.0.1` para não expor nada.

## Grupos de serviços

Um projeto raramente é um processo só. Agrupe o app, a fila, o agendador e o Vite e controle os quatro juntos.

- A subida respeita a **ordem de dependência** (campo `boot_order`, crescente), com uma pausa configurável entre um serviço e o próximo.
- A parada usa a **ordem inversa** — derruba quem depende antes de derrubar a dependência.
- A ação **Atalhos do projeto**, em um serviço Laravel, cria `queue:work`, `schedule:work` e `pail` já no mesmo grupo e na ordem certa.

## Health check e alertas

Porta aberta não quer dizer aplicação respondendo — um app que sobe e quebra no boot mantém a porta ocupada e mente para qualquer checagem de porta.

Ative o health check no serviço e informe o caminho (`/up`, por exemplo) e o status esperado. O DevPilot passa a bater na URL e classifica o resultado: **saudável**, **degradado** (já falhou, ainda abaixo do limite) ou **com falha**.

Os alertas chegam no sino do painel quando:

- um serviço cai de forma inesperada;
- o limite de reinícios (`max_restarts`) é atingido;
- CPU ou memória passam do limite definido no serviço;
- o health check acumula falhas consecutivas suficientes.

Cada alerta tem janela de silêncio (padrão: 10 minutos) para não repetir a mesma notificação em loop, e pode ser desligado por serviço.

## Logs

Cada serviço tem dois destinos de log, e os dois são úteis:

| Onde | Para quê |
|---|---|
| **Console ao vivo** | Ver o que está saindo agora, como num terminal. |
| **Histórico** (banco) | Buscar por texto e filtrar por nível ou origem depois que o problema já passou. |

A captura é incremental: o ingestor lê apenas o que foi acrescentado ao arquivo desde a última passagem, e nunca grava linha pela metade. O nível é inferido do conteúdo — inclusive pelo status HTTP no log de acesso do `php -S`, porque ali um `200` sai no stderr e não é erro nenhum.

A rotação trunca arquivos que passam do tamanho limite (só depois de ingeridos) e apaga os órfãos antigos:

```bash
php artisan services:logs-rotate
```

O agente já faz isso periodicamente.

## Comandos do projeto

A página **Comandos** roda comandos de uma tacada só na pasta do projeto — `migrate`, `optimize:clear`, `filament:upgrade`, `composer install`, `npm run build` e mais 20 outros, agrupados por finalidade.

- A lista é **filtrada pela pasta**: um projeto sem Filament não vê `filament:upgrade`; um sem `package.json` não vê `npm run build`.
- Comandos **destrutivos** (`migrate:fresh --seed`, `migrate:rollback`, `db:seed`, `queue:flush`) ficam escondidos atrás de um botão e ainda pedem confirmação.
- O catálogo é **fechado** e vive em `config/services_manager.php`: dá para adicionar comandos, mas não existe campo de linha de comando livre no painel.
- A execução herda o mesmo ambiente limpo dos serviços — um `php artisan migrate` roda contra o banco **do projeto**, nunca o do DevPilot.

Cada execução fica registrada no histórico de logs dos serviços daquela pasta.

## Configuração

Tudo em `config/services_manager.php`, com as chaves de ambiente no `.env.example`:

| Chave | Padrão | Para quê |
|---|---|---|
| `DEV_ROOTS` | `C:\laragon\www` | Raízes navegáveis. Limite de segurança do seletor de pastas e da página de Comandos. |
| `SERVICE_PORT_START` / `_END` | `8000` / `8099` | Faixa de sugestão de porta livre. |
| `SERVICE_DEFAULT_HOST` | `127.0.0.1` | Host usado ao sugerir o comando de um projeto detectado. |
| `AGENT_INTERVAL` | `3` | Segundos entre ciclos de supervisão. |
| `AGENT_TASK_NAME` / `AGENT_SERVICE_NAME` | `DevPilot-Agent` / `DevPilotAgent` | Nome do registro no Windows. |
| `NSSM_PATH` | `nssm` | Caminho do `nssm.exe`, se usar `--method=nssm`. |
| `SERVICE_LOGS_INGEST` | `true` | Liga a captura de stdout/stderr para o banco. |
| `SERVICE_LOGS_MAX_ROWS` | `5000` | Retenção do histórico, por serviço. |
| `SERVICE_LOGS_ROTATE_MB` | `10` | Tamanho a partir do qual o arquivo é truncado. |
| `SERVICE_LOGS_RETENTION_DAYS` | `7` | Prazo para apagar arquivos de log órfãos. |
| `HEALTH_CHECK_INTERVAL` | `30` | Segundos entre health checks do mesmo serviço. |
| `HEALTH_CHECK_FAILURES` | `3` | Falhas consecutivas para marcar "com falha" e alertar. |
| `SERVICE_ALERTS` | `true` | Liga os alertas do painel. |
| `SERVICE_ALERTS_COOLDOWN` | `10` | Minutos de silêncio antes de repetir o mesmo alerta. |
| `PROJECT_COMMAND_TIMEOUT` | `300` | Tempo limite dos comandos do projeto, em segundos. |

## Arquitetura

| Componente | Responsabilidade |
|---|---|
| `ProcessManager` | Lança, para e mata processos via PowerShell; resolve executáveis; limpa portas |
| `PowerShell` | Runner de scripts (`-EncodedCommand` em UTF-16LE), compartilhado |
| `MetricsCollector` | CPU/memória/porta em uma única sondagem; fonte única para painel e agente |
| `ServiceAgent` | Supervisiona, detecta quedas, aplica a política de reinício e dispara as rotinas periódicas |
| `AgentInstaller` | Registra o agente no Windows (Agendador de Tarefas ou NSSM) |
| `ServiceOrchestrator` | Operações em lote e por grupo, com ordem de dependência |
| `LogIngestor` / `LogRotator` | Captura incremental de stdout/stderr e rotação dos arquivos |
| `HealthChecker` | Health check HTTP com limiar de falhas |
| `AlertManager` | Notificações do painel, com janela de silêncio |
| `HostEnvironment` | Impede o `.env` do DevPilot de vazar para os processos filhos |
| `NetworkAddresses` / `CommandRewriter` | Detecção de IPs e troca de host dentro do comando |
| `ProjectCommandCatalog` / `ProjectCommandRunner` | Catálogo fechado de comandos e sua execução na pasta do projeto |
| `DirectoryBrowser` | Navegação de pastas restrita às raízes, com detecção de tipo de projeto |
| `PortAllocator` | Sugere a próxima porta livre |

Documentação técnica detalhada, incluindo as armadilhas de PowerShell no Windows que custaram caro: [`SISTEMA.md`](SISTEMA.md).

## Testes

```bash
php artisan test
```

55 testes. `tests/Feature/PanelSmokeTest.php` renderiza todas as telas e widgets — é a rede de segurança contra uso incorreto da API do Filament, que só falha em tempo de renderização. Os demais cobrem ingestão de logs, ordem de subida dos grupos, health check, alertas, seleção de host e execução de comandos.

## Segurança

Este painel **executa comandos no servidor**. Ele foi feito para rodar na sua máquina de desenvolvimento, atrás de autenticação e **nunca exposto à internet**. Se precisar expor na rede local, restrinja o acesso por firewall.

Dois limites embutidos:

- O seletor de pastas e a página de Comandos só enxergam o que estiver dentro de `DEV_ROOTS`.
- A página de Comandos executa apenas o catálogo definido em configuração — não há campo de linha de comando livre.

## Licença

[MIT](LICENSE) — use, modifique e redistribua livremente, inclusive comercialmente, mantendo o aviso de copyright.

Copyright © 2026 **EudesSA - ProezaTech** — [www.proezatech.com](https://www.proezatech.com)
