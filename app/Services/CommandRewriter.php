<?php

namespace App\Services;

use Illuminate\Support\Str;

/**
 * Leitura e troca do host dentro da linha de comando do serviço.
 *
 * O host não é uma coluna: ele vive no comando (`--host=`, `-S host:porta`),
 * que continua sendo a fonte única de verdade. O seletor do formulário apenas
 * reescreve essa parte, para o usuário não precisar editar a linha na mão.
 */
class CommandRewriter
{
    /**
     * Host declarado no comando, ou `null` se o comando não fala de host.
     *
     * Diferente de `Service::resolvedHost()`, aqui `0.0.0.0` é devolvido como
     * está — o formulário precisa mostrar a opção que o usuário escolheu, e
     * não o endereço pelo qual o link de acesso é montado.
     */
    public static function extractHost(?string $command): ?string
    {
        $command = (string) $command;

        if (preg_match('/--host[= ]([^\s"\']+)/i', $command, $m)) {
            return $m[1];
        }

        if (preg_match('/-S\s+([^\s:"\']+):\d+/i', $command, $m)) {
            return $m[1];
        }

        return null;
    }

    /**
     * Devolve o comando escutando em `$host`.
     *
     * Se o comando já declara um host, ele é trocado; se é um `artisan serve`
     * sem host, o parâmetro é acrescentado; nos demais casos o comando volta
     * intacto — não dá para adivinhar a sintaxe de host de um script qualquer.
     */
    public static function withHost(?string $command, ?string $host): string
    {
        $command = trim((string) $command);

        if ($command === '' || $host === null || $host === '') {
            return $command;
        }

        if (preg_match('/--host=([^\s"\']+)/i', $command)) {
            return (string) preg_replace('/--host=([^\s"\']+)/i', '--host=' . $host, $command, 1);
        }

        if (preg_match('/--host\s+([^\s"\']+)/i', $command)) {
            return (string) preg_replace('/--host\s+([^\s"\']+)/i', '--host ' . $host, $command, 1);
        }

        if (preg_match('/-S\s+([^\s:"\']+):(\d+)/i', $command, $m)) {
            return (string) preg_replace(
                '/-S\s+([^\s:"\']+):(\d+)/i',
                '-S ' . $host . ':' . $m[2],
                $command,
                1,
            );
        }

        if (Str::contains($command, 'artisan serve', ignoreCase: true)) {
            return $command . ' --host=' . $host;
        }

        return $command;
    }

    /**
     * Indica se faz sentido oferecer o seletor de host para este comando.
     */
    public static function supportsHost(?string $command): bool
    {
        $command = (string) $command;

        return static::extractHost($command) !== null
            || Str::contains($command, 'artisan serve', ignoreCase: true)
            || Str::contains($command, '-S ', ignoreCase: true);
    }
}
