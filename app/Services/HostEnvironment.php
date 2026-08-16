<?php

namespace App\Services;

use Illuminate\Support\Facades\File;

/**
 * Ambiente entregue aos processos filhos.
 *
 * O Laravel do próprio Gerenciador exporta o seu `.env` para o ambiente do
 * processo (via `putenv` do Dotenv), e todo filho herda isso. Como o Dotenv
 * **não sobrescreve** variáveis já presentes no ambiente, o `.env` do projeto
 * filho era simplesmente ignorado — um `php artisan migrate` no projeto de
 * destino rodaria contra o banco do Gerenciador.
 *
 * A limpeza é feita do lado do PHP (valor `false` no array do Symfony Process),
 * nunca com `Remove-Item Env:\...` dentro do script PowerShell: esse padrão é
 * bloqueado silenciosamente pelo Defender/AMSI em `-EncodedCommand`.
 */
class HostEnvironment
{
    /**
     * Nomes das variáveis definidas no `.env` deste app.
     *
     * @return list<string>
     */
    public static function keysToClear(): array
    {
        static $keys = null;

        if ($keys !== null) {
            return $keys;
        }

        $keys = [];
        $path = base_path('.env');

        if (File::exists($path)) {
            foreach (explode("\n", File::get($path)) as $line) {
                $line = trim($line);

                if ($line === '' || str_starts_with($line, '#')) {
                    continue;
                }

                if (preg_match('/^([A-Za-z_][A-Za-z0-9_]*)\s*=/', $line, $m)) {
                    $keys[] = $m[1];
                }
            }
        }

        return $keys = array_values(array_unique($keys));
    }

    /**
     * Array de ambiente para o processo filho: remove o que veio do `.env` do
     * Gerenciador e aplica as variáveis extras informadas.
     *
     * @param  array<string, string|int|null>  $extra
     * @return array<string, string|false>
     */
    public static function forChild(array $extra = []): array
    {
        $env = array_fill_keys(static::keysToClear(), false);

        foreach ($extra as $key => $value) {
            if ($key === '' || ! is_string($key)) {
                continue;
            }

            $env[$key] = (string) $value;
        }

        return $env;
    }
}
