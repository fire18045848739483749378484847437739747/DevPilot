# DevPilot

Painel web para iniciar, parar e monitorar processos de desenvolvimento no Windows.

Feito para quem roda vários projetos Laravel ao mesmo tempo e se perde entre terminais abertos, portas ocupadas e processos órfãos.

![Licença](https://img.shields.io/badge/licen%C3%A7a-MIT-22c55e)
![PHP](https://img.shields.io/badge/PHP-8.3%2B-777bb4)
![Laravel](https://img.shields.io/badge/Laravel-13.x-ff2d20)
![Filament](https://img.shields.io/badge/Filament-5.x-fdae4b)

---

## Recursos

- **Controle total pelo navegador** — iniciar, parar, reiniciar e matar processos, individualmente ou todos de uma vez.
- **Monitoramento ao vivo** — PID, CPU, memória, uptime e estado da porta, com histórico em gráficos. Funciona **sem** o agente rodando.
- **Reinício automático** — o agente detecta quedas e sobe o processo de novo, respeitando política e limite de tentativas.
- **Mapa de portas** — veja quais portas estão livres, ocupadas por um serviço ou por um processo externo.
- **Seletor de pastas** — navegue pelos projetos do servidor em vez de digitar caminhos, restrito às raízes que você definir.
- **Logs em tempo real** — stdout/stderr no navegador, com destaque de erros e limpeza com um clique.
- **Sem processos órfãos** — ao parar, o filho `php -S` do `artisan serve` também morre; a porta fica realmente livre.
- **Ambiente isolado** — cada serviço usa o próprio `.env`; as variáveis do gerenciador não vazam para os processos filhos.

## Requisitos

| Componente | Versão | Observação |
|---|---|---|
| PHP | 8.3+ | com a extensão `pdo` |
| Laravel | 13.x | — |
| Filament | 5.x | painel administrativo |
| Banco | MySQL 8 ou SQLite | ambos suportados |
| Sistema | Windows 10/11 | usa PowerShell para controlar processos |

## Instalação

```bash
git clone <url-do-repositorio>
cd gerenciador-de-processos
composer install
```

```bash
cp .env.example .env
php artisan key:generate
```

Ajuste as credenciais do banco no `.env`. Opcionalmente, defina as raízes navegáveis pelo seletor de pastas e a faixa de portas sugeridas:

```dotenv
DEV_ROOTS=C:\laragon\www
SERVICE_PORT_START=8000
SERVICE_PORT_END=8099
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

O painel coleta métricas sozinho. O agente é necessário apenas para **reinício automático** e para **iniciar serviços no boot** (`auto_start_on_boot`).

```bash
# loop contínuo
php artisan services:agent

# um único ciclo — útil no Agendador de Tarefas do Windows
php artisan services:agent --once

# intervalo personalizado, em segundos
php artisan services:agent --interval=5
```

Para que sobreviva a reinicializações, registre-o como serviço do Windows (via [NSSM](https://nssm.cc/)) ou como Tarefa Agendada disparada no logon.

## Cadastrando um serviço

| Campo | Exemplo |
|---|---|
| Nome | `DEV_meu-projeto` |
| Comando | `php artisan serve --host=192.168.0.11 --port=8000` |
| Diretório de execução | `C:\laragon\www\meu-projeto` |
| Porta | `8000` |
| Caminho inicial | `admin` (opcional — compõe o link de acesso) |

O binário do `php` é resolvido automaticamente a partir de `PHP_BINARY`, então não é preciso informar o caminho completo.

## Arquitetura

| Componente | Responsabilidade |
|---|---|
| `ProcessManager` | Lança, para e mata processos via PowerShell; resolve executáveis; limpa portas |
| `MetricsCollector` | Coleta CPU/memória/porta em uma única sondagem; fonte única para painel e agente |
| `ServiceAgent` | Supervisiona, detecta quedas e aplica a política de reinício |
| `ServiceOrchestrator` | Operações em lote (iniciar/parar/reiniciar todos) |
| `DirectoryBrowser` | Navegação de pastas restrita às raízes configuradas |
| `PortAllocator` | Sugere a próxima porta livre |

Documentação técnica detalhada, incluindo armadilhas de PowerShell no Windows: [`SISTEMA.md`](SISTEMA.md).

## Testes

```bash
php artisan test
```

`tests/Feature/PanelSmokeTest.php` renderiza todas as telas e widgets — a rede de segurança contra uso incorreto da API do Filament, que só falha em tempo de renderização.

## Segurança

Este painel **executa comandos arbitrários no servidor**. Ele foi feito para rodar na sua máquina de desenvolvimento, atrás de autenticação e **nunca exposto à internet**. Se precisar expor na rede local, restrinja o acesso por firewall.

## Licença

[MIT](LICENSE) — use, modifique e redistribua livremente, inclusive comercialmente, mantendo o aviso de copyright.

Copyright © 2026 **EudesSA - ProezaTech** — [www.proezatech.com](https://www.proezatech.com)

