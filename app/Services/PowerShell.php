<?php

namespace App\Services;

use Symfony\Component\Process\Process;

/**
 * Execução de scripts PowerShell.
 *
 * Extraído do ProcessManager para que outros pontos (registro do agente como
 * serviço do Windows, por exemplo) usem exatamente a mesma forma de invocação:
 * `-EncodedCommand` em UTF-16LE, sem perfil e não interativo.
 *
 * Armadilhas conhecidas (ver SISTEMA.md):
 * - `$pid` é somente-leitura no PowerShell; use outro nome de variável.
 * - `Remove-Item Env:\...` dentro de `-EncodedCommand` é bloqueado pelo AMSI;
 *   manipule o ambiente do lado do PHP (parâmetro `$env`).
 */
class PowerShell
{
    /**
     * @param  array<string, string|false>|null  $env  Variáveis extras/sobrescritas para o processo.
     *                                                  Valor `false` remove a variável (não repassa a do processo atual).
     *                                                  `null` (padrão) herda o ambiente atual sem alterações.
     * @return array{output: list<string>, error: string, exit: int}
     */
    public function run(string $script, int $timeout = 60, ?array $env = null): array
    {
        $encoded = base64_encode(mb_convert_encoding($script, 'UTF-16LE', 'UTF-8'));

        $process = new Process([
            'powershell',
            '-NoProfile',
            '-NonInteractive',
            '-ExecutionPolicy',
            'Bypass',
            '-EncodedCommand',
            $encoded,
        ], null, $env);

        $process->setTimeout($timeout);
        $process->run();

        return [
            'output' => explode("\n", $process->getOutput()),
            'error' => trim($process->getErrorOutput()),
            'exit' => (int) $process->getExitCode(),
        ];
    }

    /**
     * Saída do script já concatenada e sem espaços nas pontas.
     */
    public function text(string $script, int $timeout = 60): string
    {
        return trim(implode("\n", $this->run($script, $timeout)['output']));
    }

    /**
     * Literal de string do PowerShell (aspas simples, com escape de `'`).
     */
    public function str(string $value): string
    {
        return "'" . str_replace("'", "''", $value) . "'";
    }

    /**
     * Indica se o processo atual está rodando com privilégios de administrador.
     * O registro de tarefa/serviço do Windows exige elevação.
     */
    public function isElevated(): bool
    {
        $script = "[Console]::OutputEncoding = [System.Text.Encoding]::UTF8;"
            . "\$identity = [Security.Principal.WindowsIdentity]::GetCurrent();"
            . "\$principal = New-Object Security.Principal.WindowsPrincipal(\$identity);"
            . "if (\$principal.IsInRole([Security.Principal.WindowsBuiltInRole]::Administrator)) { 'ELEVATED' } else { 'NORMAL' }";

        return str_contains($this->text($script, 20), 'ELEVATED');
    }
}
