@php
    $project = config('services_manager.project');
    $repository = $project['repository'] ?? '';
    $website = $project['website'] ?? '';
    $author = $project['author'] ?? '';
    $portRange = config('services_manager.port_range');
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="{{ $project['tagline'] }}">
    <meta name="color-scheme" content="dark light">

    <title>{{ $project['name'] }}</title>

    <link rel="icon"
        href="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'><text y='.9em' font-size='90'>&#9889;</text></svg>">

    <style>
        :root {
            --bg: #070b09;
            --panel: #0f1613;
            --panel-2: #0b110f;
            --border: #1c2b24;
            --green: #22c55e;
            --green-dim: #16a34a;
            --cyan: #06b6d4;
            --text: #d7f5dc;
            --muted: #7b9c8a;
            --mono: 'JetBrains Mono', 'Fira Code', 'Cascadia Code', ui-monospace,
                SFMono-Regular, Menlo, Consolas, monospace;
            --sans: ui-sans-serif, system-ui, -apple-system, 'Segoe UI', Roboto, sans-serif;
        }

        *,
        *::before,
        *::after {
            box-sizing: border-box;
        }

        html {
            scroll-behavior: smooth;
        }

        body {
            margin: 0;
            background: var(--bg);
            background-image:
                radial-gradient(ellipse 80% 50% at 50% -10%, rgba(34, 197, 94, 0.10), transparent),
                linear-gradient(rgba(34, 197, 94, 0.012) 50%, transparent 50%);
            background-size: 100% 100%, 100% 3px;
            color: var(--text);
            font-family: var(--sans);
            line-height: 1.6;
            -webkit-font-smoothing: antialiased;
        }

        a {
            color: var(--green);
            text-decoration: none;
        }

        a:hover {
            text-decoration: underline;
        }

        .wrap {
            width: min(1080px, 100% - 2.5rem);
            margin-inline: auto;
        }

        /* ---------------------------------------------------------- header */

        header {
            position: sticky;
            top: 0;
            z-index: 10;
            backdrop-filter: blur(10px);
            background: rgba(7, 11, 9, 0.8);
            border-bottom: 1px solid var(--border);
        }

        .nav {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 1rem;
            padding: 0.85rem 0;
            flex-wrap: wrap;
        }

        .brand {
            font-family: var(--mono);
            font-weight: 700;
            color: var(--green);
            font-size: 0.95rem;
            letter-spacing: -0.01em;
        }

        .brand span {
            color: var(--green-dim);
        }

        .nav-links {
            display: flex;
            gap: 1.25rem;
            align-items: center;
            flex-wrap: wrap;
        }

        .nav-links a {
            color: var(--muted);
            font-size: 0.9rem;
        }

        .nav-links a:hover {
            color: var(--text);
            text-decoration: none;
        }

        /* ------------------------------------------------------------ hero */

        .hero {
            padding: clamp(3rem, 9vw, 6rem) 0 3rem;
        }

        .badge {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            font-family: var(--mono);
            font-size: 0.74rem;
            color: var(--green);
            background: rgba(34, 197, 94, 0.10);
            border: 1px solid rgba(34, 197, 94, 0.30);
            border-radius: 999px;
            padding: 0.3rem 0.75rem;
            margin-bottom: 1.25rem;
        }

        .dot {
            width: 0.45rem;
            height: 0.45rem;
            border-radius: 999px;
            background: var(--green);
            box-shadow: 0 0 8px var(--green);
            animation: pulse 2s ease-in-out infinite;
        }

        @keyframes pulse {

            0%,
            100% {
                opacity: 1
            }

            50% {
                opacity: 0.35
            }
        }

        h1 {
            font-family: var(--mono);
            font-size: clamp(1.9rem, 5.5vw, 3.25rem);
            line-height: 1.12;
            margin: 0 0 1rem;
            letter-spacing: -0.03em;
            color: #eafff0;
        }

        h1 em {
            color: var(--green);
            font-style: normal;
        }

        .lead {
            font-size: clamp(1rem, 2.2vw, 1.15rem);
            color: var(--muted);
            max-width: 60ch;
            margin: 0 0 2rem;
        }

        .cta {
            display: flex;
            gap: 0.75rem;
            flex-wrap: wrap;
        }

        .btn {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            font-family: var(--mono);
            font-size: 0.88rem;
            font-weight: 600;
            padding: 0.7rem 1.25rem;
            border-radius: 0.5rem;
            border: 1px solid transparent;
            transition: transform 120ms ease, box-shadow 120ms ease, background 120ms ease;
        }

        .btn:hover {
            text-decoration: none;
            transform: translateY(-1px);
        }

        .btn-primary {
            background: var(--green);
            color: #04180c;
            box-shadow: 0 0 22px rgba(34, 197, 94, 0.28);
        }

        .btn-primary:hover {
            background: #2fd968;
        }

        .btn-ghost {
            border-color: var(--border);
            color: var(--text);
            background: var(--panel);
        }

        .btn-ghost:hover {
            border-color: var(--green-dim);
        }

        /* -------------------------------------------------------- terminal */

        .terminal {
            margin-top: 3rem;
            background: var(--panel-2);
            border: 1px solid var(--border);
            border-radius: 0.75rem;
            overflow: hidden;
            box-shadow: 0 24px 60px -20px rgba(0, 0, 0, 0.8);
        }

        .terminal-bar {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.65rem 0.9rem;
            background: var(--panel);
            border-bottom: 1px solid var(--border);
        }

        .tdot {
            width: 0.7rem;
            height: 0.7rem;
            border-radius: 999px;
        }

        .terminal-title {
            font-family: var(--mono);
            font-size: 0.75rem;
            color: var(--muted);
            margin-left: 0.5rem;
        }

        .terminal pre {
            margin: 0;
            padding: 1.1rem 1.25rem;
            font-family: var(--mono);
            font-size: 0.78rem;
            line-height: 1.75;
            color: var(--text);
            overflow-x: auto;
        }

        .c-prompt {
            color: var(--green);
        }

        .c-muted {
            color: var(--muted);
        }

        .c-cyan {
            color: var(--cyan);
        }

        .c-warn {
            color: #fbbf24;
        }

        /* -------------------------------------------------------- sections */

        section {
            padding: clamp(2.5rem, 7vw, 4.5rem) 0;
        }

        h2 {
            font-family: var(--mono);
            font-size: clamp(1.3rem, 3.2vw, 1.75rem);
            margin: 0 0 0.6rem;
            letter-spacing: -0.02em;
            color: #eafff0;
        }

        h2::before {
            content: '# ';
            color: var(--green-dim);
        }

        .section-lead {
            color: var(--muted);
            margin: 0 0 2rem;
            max-width: 62ch;
        }

        .grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(min(100%, 270px), 1fr));
            gap: 1rem;
        }

        .card {
            background: var(--panel);
            border: 1px solid var(--border);
            border-radius: 0.75rem;
            padding: 1.35rem;
            transition: border-color 140ms ease, transform 140ms ease;
        }

        .card:hover {
            border-color: rgba(34, 197, 94, 0.4);
            transform: translateY(-2px);
        }

        .card h3 {
            font-family: var(--mono);
            font-size: 0.95rem;
            margin: 0 0 0.5rem;
            color: var(--green);
        }

        .card p {
            margin: 0;
            color: var(--muted);
            font-size: 0.9rem;
        }

        .icon {
            display: grid;
            place-items: center;
            width: 2.25rem;
            height: 2.25rem;
            border-radius: 0.5rem;
            background: rgba(34, 197, 94, 0.12);
            margin-bottom: 0.9rem;
            font-size: 1.05rem;
        }

        /* ------------------------------------------------------------ steps */

        ol.steps {
            counter-reset: step;
            list-style: none;
            padding: 0;
            margin: 0;
        }

        ol.steps li {
            counter-increment: step;
            position: relative;
            padding-left: 2.75rem;
            margin-bottom: 1.5rem;
        }

        ol.steps li::before {
            content: counter(step);
            position: absolute;
            left: 0;
            top: 0;
            width: 1.85rem;
            height: 1.85rem;
            display: grid;
            place-items: center;
            border-radius: 0.45rem;
            background: rgba(34, 197, 94, 0.12);
            border: 1px solid rgba(34, 197, 94, 0.3);
            color: var(--green);
            font-family: var(--mono);
            font-size: 0.8rem;
            font-weight: 700;
        }

        ol.steps strong {
            display: block;
            margin-bottom: 0.4rem;
            font-weight: 600;
        }

        code,
        pre.block {
            font-family: var(--mono);
            font-size: 0.8rem;
        }

        code.inline {
            background: var(--panel-2);
            border: 1px solid var(--border);
            border-radius: 0.3rem;
            padding: 0.1rem 0.4rem;
            color: var(--green);
        }

        pre.block {
            background: var(--panel-2);
            border: 1px solid var(--border);
            border-radius: 0.5rem;
            padding: 0.8rem 1rem;
            overflow-x: auto;
            margin: 0.5rem 0 0;
            color: var(--text);
            line-height: 1.7;
        }

        /* ------------------------------------------------------------ table */

        .req {
            width: 100%;
            border-collapse: collapse;
            font-size: 0.9rem;
        }

        .req th,
        .req td {
            text-align: left;
            padding: 0.7rem 0.9rem;
            border-bottom: 1px solid var(--border);
        }

        .req th {
            color: var(--muted);
            font-weight: 600;
            font-size: 0.78rem;
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }

        .req td {
            font-family: var(--mono);
            font-size: 0.83rem;
        }

        .req tr:last-child td {
            border-bottom: none;
        }

        /* ----------------------------------------------------------- footer */

        footer {
            border-top: 1px solid var(--border);
            padding: 2.25rem 0 3rem;
            color: var(--muted);
            font-size: 0.85rem;
        }

        .footer-row {
            display: flex;
            justify-content: space-between;
            gap: 1rem;
            flex-wrap: wrap;
        }

        .note {
            background: rgba(251, 191, 36, 0.08);
            border: 1px solid rgba(251, 191, 36, 0.28);
            border-radius: 0.5rem;
            padding: 0.85rem 1rem;
            font-size: 0.86rem;
            color: #fde68a;
            margin-top: 1.25rem;
        }

        .note strong {
            color: #fbbf24;
        }

        @media (prefers-reduced-motion: reduce) {
            html {
                scroll-behavior: auto;
            }

            *,
            *::before,
            *::after {
                animation: none !important;
                transition: none !important;
            }
        }
    </style>
</head>

<body>

    <header>
        <div class="wrap nav">
            <div class="brand">&gt;_ {{ $project['name'] }}<span>.v{{ $project['version'] }}</span></div>
            <nav class="nav-links">
                <a href="#recursos">Recursos</a>
                <a href="#instalacao">Instalação</a>
                <a href="#agente">Agente</a>
                <a href="#licenca">Licença</a>
                @if ($repository)
                    <a href="{{ $repository }}" target="_blank" rel="noopener noreferrer">GitHub</a>
                @endif
            </nav>
        </div>
    </header>

    <main>
        <div class="wrap hero">
            <div class="badge"><span class="dot"></span> Open source &middot; Licença {{ $project['license'] }}</div>

            <h1>Gerencie seus processos de <em>desenvolvimento</em> pelo navegador.</h1>

            <p class="lead">{{ $project['tagline'] }}</p>

            <div class="cta">
                <a class="btn btn-primary" href="/admin">Abrir o painel &rarr;</a>
                @if ($repository)
                    <a class="btn btn-ghost" href="{{ $repository }}" target="_blank" rel="noopener noreferrer">Ver no
                        GitHub</a>
                @endif
            </div>

            <div class="terminal">
                <div class="terminal-bar">
                    <span class="tdot" style="background:#ff5f57"></span>
                    <span class="tdot" style="background:#febc2e"></span>
                    <span class="tdot" style="background:#28c840"></span>
                    <span class="terminal-title">powershell — {{ $project['name'] }}</span>
                </div>
                <pre><span class="c-prompt">PS&gt;</span> php artisan serve --host=192.168.0.11 --port=8000
<span class="c-muted">  Iniciando serviço "DEV_meu-projeto"...</span>
<span class="c-cyan">  INFO</span>  Server running on [http://192.168.0.11:8000]

<span class="c-prompt">PS&gt;</span> php artisan services:agent
<span class="c-muted">  Supervisionando 4 serviços (heartbeat 5s)</span>
<span class="c-cyan">  OK</span>    DEV_meu-projeto      pid=14880  cpu=0.4%   mem=74.4 MB  :8000 <span class="c-prompt">aberta</span>
<span class="c-cyan">  OK</span>    API_Interna     pid=15012  cpu=1.2%   mem=52.1 MB  :8001 <span class="c-prompt">aberta</span>
<span class="c-warn">  WARN</span>  Worker_Queue    caiu — reiniciando (1 de 5)...
<span class="c-cyan">  OK</span>    Worker_Queue    pid=15340  cpu=0.1%   mem=38.7 MB</pre>
            </div>
        </div>

        <section id="recursos">
            <div class="wrap">
                <h2>Recursos</h2>
                <p class="section-lead">
                    Pensado para quem roda vários projetos Laravel ao mesmo tempo no Windows e se perde
                    entre terminais abertos, portas ocupadas e processos órfãos.
                </p>

                <div class="grid">
                    <article class="card">
                        <div class="icon">▶</div>
                        <h3>Iniciar, parar, reiniciar</h3>
                        <p>Controle cada processo pelo painel — individualmente ou todos de uma vez, com um clique.</p>
                    </article>

                    <article class="card">
                        <div class="icon">📊</div>
                        <h3>Monitoramento ao vivo</h3>
                        <p>PID, CPU, memória, uptime e estado da porta atualizados em tempo real, com histórico em
                            gráficos.</p>
                    </article>

                    <article class="card">
                        <div class="icon">🔁</div>
                        <h3>Reinício automático</h3>
                        <p>O agente detecta quedas e sobe o processo de novo, respeitando a política e o limite de
                            tentativas.</p>
                    </article>

                    <article class="card">
                        <div class="icon">🔌</div>
                        <h3>Mapa de portas</h3>
                        <p>Veja de relance quais portas estão livres, ocupadas por um serviço ou por um processo
                            externo.</p>
                    </article>

                    <article class="card">
                        <div class="icon">📁</div>
                        <h3>Seletor de pastas</h3>
                        <p>Navegue pelos projetos do servidor em vez de digitar caminhos — restrito às raízes que você
                            definir.</p>
                    </article>

                    <article class="card">
                        <div class="icon">📜</div>
                        <h3>Logs em tempo real</h3>
                        <p>Acompanhe stdout e stderr direto no navegador, com destaque de erros e limpeza com um clique.
                        </p>
                    </article>

                    <article class="card">
                        <div class="icon">🧹</div>
                        <h3>Sem processos órfãos</h3>
                        <p>Ao parar, o filho <code class="inline">php -S</code> do <code
                                class="inline">artisan serve</code> também morre — a porta fica realmente livre.</p>
                    </article>

                    <article class="card">
                        <div class="icon">🔒</div>
                        <h3>Ambiente isolado</h3>
                        <p>Cada serviço usa o próprio <code class="inline">.env</code>: as variáveis do gerenciador não
                            vazam para os processos filhos.</p>
                    </article>
                </div>
            </div>
        </section>

        <section id="instalacao">
            <div class="wrap">
                <h2>Instalação</h2>
                <p class="section-lead">Requer PHP 8.3+, Composer e um banco MySQL ou SQLite.</p>

                <ol class="steps">
                    <li>
                        <strong>Clone e instale as dependências</strong>
                        <pre class="block">{{ $repository ? 'git clone ' . $repository : 'git clone <url-do-repositorio>' }}
cd {{ \Illuminate\Support\Str::slug($project['name']) }}
composer install</pre>
                    </li>

                    <li>
                        <strong>Configure o ambiente</strong>
                        <pre class="block">cp .env.example .env
php artisan key:generate</pre>
                        <p style="color:var(--muted);font-size:0.87rem;margin:.6rem 0 0">
                            Ajuste as credenciais do banco e, se quiser, as raízes navegáveis pelo seletor de pastas:
                            <code class="inline">DEV_ROOTS=C:\laragon\www</code>
                        </p>
                    </li>

                    <li>
                        <strong>Crie as tabelas e o usuário administrador</strong>
                        <pre class="block">php artisan migrate
php artisan make:filament-user</pre>
                    </li>

                    <li>
                        <strong>Suba a aplicação</strong>
                        <pre class="block">php artisan serve</pre>
                        <p style="color:var(--muted);font-size:0.87rem;margin:.6rem 0 0">
                            O painel fica em <code class="inline">/admin</code>.
                        </p>
                    </li>
                </ol>

                <div class="note">
                    <strong>Atenção:</strong> o modelo <code class="inline">User</code> não implementa
                    <code class="inline">FilamentUser</code>, então o painel só abre quando
                    <code class="inline">APP_ENV=local</code>. Para publicar em outro ambiente, implemente
                    <code class="inline">canAccessPanel()</code> no seu modelo de usuário.
                </div>
            </div>
        </section>

        <section id="agente">
            <div class="wrap">
                <h2>Agente de supervisão</h2>
                <p class="section-lead">
                    O painel monitora os processos sozinho. O agente é necessário apenas para
                    <strong>reinício automático</strong> e para <strong>iniciar serviços no boot</strong>.
                </p>

                <pre class="block"><span class="c-muted"># loop contínuo (deixe rodando em segundo plano)</span>
php artisan services:agent

<span class="c-muted"># um único ciclo — útil no Agendador de Tarefas do Windows</span>
php artisan services:agent --once

<span class="c-muted"># intervalo personalizado, em segundos</span>
php artisan services:agent --interval=5</pre>

                <p style="color:var(--muted);font-size:0.9rem;margin-top:1.25rem">
                    Para que ele sobreviva a reinicializações, registre-o como serviço do Windows
                    (NSSM) ou como Tarefa Agendada disparada no logon.
                </p>
            </div>
        </section>

        <section id="requisitos">
            <div class="wrap">
                <h2>Requisitos</h2>
                <div class="card" style="padding:0.35rem 0.5rem">
                    <table class="req">
                        <thead>
                            <tr>
                                <th>Componente</th>
                                <th>Versão</th>
                                <th>Observação</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td>PHP</td>
                                <td>8.3+</td>
                                <td>com a extensão <code class="inline">pdo</code></td>
                            </tr>
                            <tr>
                                <td>Laravel</td>
                                <td>13.x</td>
                                <td>—</td>
                            </tr>
                            <tr>
                                <td>Filament</td>
                                <td>5.x</td>
                                <td>painel administrativo</td>
                            </tr>
                            <tr>
                                <td>Banco</td>
                                <td>MySQL 8 / SQLite</td>
                                <td>ambos suportados</td>
                            </tr>
                            <tr>
                                <td>Sistema</td>
                                <td>Windows 10/11</td>
                                <td>usa PowerShell para controlar processos</td>
                            </tr>
                            <tr>
                                <td>Portas</td>
                                <td>{{ $portRange['start'] }}–{{ $portRange['end'] }}</td>
                                <td>faixa padrão sugerida</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </section>

        <section id="licenca">
            <div class="wrap">
                <h2>Licença</h2>
                <p class="section-lead">
                    Distribuído sob a licença <strong>{{ $project['license'] }}</strong> — use, modifique e
                    redistribua livremente, inclusive comercialmente, mantendo o aviso de copyright.
                </p>

                <p style="font-family:var(--mono);font-size:0.84rem;color:var(--muted);margin:0 0 1.5rem">
                    Copyright &copy; {{ date('Y') }} {{ $author }}
                    @if ($website)
                        &middot; <a href="{{ $website }}" target="_blank"
                            rel="noopener noreferrer">{{ preg_replace('#^https?://#', '', $website) }}</a>
                    @endif
                </p>

                @if ($repository)
                    <a class="btn btn-ghost" href="{{ rtrim($repository, '/') }}/blob/main/LICENSE" target="_blank"
                        rel="noopener noreferrer">
                        Ler a licença completa
                    </a>
                @endif
            </div>
        </section>
    </main>

    <footer>
        <div class="wrap footer-row">
            <div>
                {{ $project['name'] }} &middot; Licença {{ $project['license'] }}
                @if ($author)
                    &middot; feito por
                    @if ($website)
                        <a href="{{ $website }}" target="_blank" rel="noopener noreferrer">{{ $author }}</a>
                    @else
                        {{ $author }}
                    @endif
                @endif
            </div>
            <div>
                <a href="/admin">Painel</a>
                @if ($website)
                    &middot; <a href="{{ $website }}" target="_blank" rel="noopener noreferrer">proezatech.com</a>
                @endif
                @if ($repository)
                    &middot; <a href="{{ $repository }}" target="_blank" rel="noopener noreferrer">Código-fonte</a>
                @endif
            </div>
        </div>
    </footer>

</body>

</html>