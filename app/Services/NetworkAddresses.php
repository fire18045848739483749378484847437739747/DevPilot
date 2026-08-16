<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Throwable;

/**
 * Endereços IPv4 da máquina, para escolher em que host o serviço vai escutar.
 *
 * A máquina costuma ter muito mais IPs do que o útil: adaptadores do WSL, do
 * VirtualBox, de VPN e endereços de link-local (169.254.x) aparecem todos no
 * `Get-NetIPAddress`. A ordenação empurra os virtuais para o fim para que o
 * primeiro da lista seja o IP da rede de verdade.
 */
class NetworkAddresses
{
    public const LOCALHOST = '127.0.0.1';
    public const ALL_INTERFACES = '0.0.0.0';

    /** Trechos de nome que denunciam um adaptador virtual, não a rede real. */
    protected const VIRTUAL_HINTS = [
        'vethernet', 'wsl', 'virtualbox', 'vmware', 'hyper-v', 'loopback',
        'tap-windows', 'openvpn', 'vpn', 'docker', 'bluetooth', 'tunnel',
    ];

    /**
     * Memória do processo atual. O `Get-NetIPAddress` custa mais de um segundo
     * nesta máquina, e o Select do formulário reavalia as opções a cada render
     * do Livewire — sem isto, cada digitada no formulário pagaria esse preço.
     *
     * @var list<array{ip: string, interface: string}>|null
     */
    protected static ?array $memo = null;

    public function __construct(protected PowerShell $ps)
    {
    }

    /**
     * Opções para o seletor de host: localhost, os IPs da máquina e o curinga.
     *
     * @return array<string, string>
     */
    public function options(): array
    {
        $options = [self::LOCALHOST => __('services.hosts.localhost')];

        foreach ($this->addresses() as $address) {
            $options[$address['ip']] = $address['ip'] . ' — ' . $address['interface'];
        }

        $options[self::ALL_INTERFACES] = __('services.hosts.all_interfaces');

        return $options;
    }

    /**
     * IP mais provável de ser o da rede local (o primeiro da lista ordenada).
     */
    public function primary(): ?string
    {
        return $this->addresses()[0]['ip'] ?? null;
    }

    /**
     * @return list<array{ip: string, interface: string}>
     */
    public function addresses(): array
    {
        if (static::$memo !== null) {
            return static::$memo;
        }

        try {
            return static::$memo = Cache::remember(
                'network-addresses',
                now()->addMinutes(5),
                fn (): array => $this->detect(),
            );
        } catch (Throwable) {
            return static::$memo = $this->detect();
        }
    }

    public function forget(): void
    {
        static::$memo = null;

        try {
            Cache::forget('network-addresses');
        } catch (Throwable) {
            // Cache indisponível apenas significa detectar de novo na próxima.
        }
    }

    /**
     * @return list<array{ip: string, interface: string}>
     */
    protected function detect(): array
    {
        $addresses = $this->detectViaPowerShell();

        if ($addresses === []) {
            $addresses = $this->detectViaPhp();
        }

        usort($addresses, fn (array $a, array $b): int => $this->score($a) <=> $this->score($b) ?: strcmp($a['ip'], $b['ip']));

        return array_values($addresses);
    }

    /**
     * @return list<array{ip: string, interface: string, origin: string}>
     */
    protected function detectViaPowerShell(): array
    {
        $script = "[Console]::OutputEncoding = [System.Text.Encoding]::UTF8;"
            . "try {"
            . "  Get-NetIPAddress -AddressFamily IPv4 -ErrorAction Stop |"
            . "    ForEach-Object { Write-Output ('IP|' + \$_.IPAddress + '|' + \$_.InterfaceAlias + '|' + \$_.PrefixOrigin) }"
            . "} catch { }";

        $addresses = [];

        foreach (explode("\n", $this->ps->text($script, 20)) as $line) {
            if (! preg_match('/^IP\|([0-9.]+)\|(.*)\|([A-Za-z]+)$/', trim($line), $m)) {
                continue;
            }

            if (! $this->isUsable($m[1])) {
                continue;
            }

            $addresses[] = ['ip' => $m[1], 'interface' => trim($m[2]) ?: '—', 'origin' => $m[3]];
        }

        return $addresses;
    }

    /**
     * Alternativa sem PowerShell. Não traz o nome da interface, mas resolve em
     * máquinas onde o `Get-NetIPAddress` não existe.
     *
     * @return list<array{ip: string, interface: string, origin: string}>
     */
    protected function detectViaPhp(): array
    {
        $host = gethostname();

        if ($host === false) {
            return [];
        }

        $ips = gethostbynamel($host);

        if ($ips === false) {
            return [];
        }

        $addresses = [];

        foreach ($ips as $ip) {
            if ($this->isUsable($ip)) {
                $addresses[] = ['ip' => $ip, 'interface' => __('services.hosts.unknown_interface'), 'origin' => 'Dhcp'];
            }
        }

        return $addresses;
    }

    /**
     * Descarta loopback e link-local (169.254.x, o endereço de "não consegui
     * falar com o DHCP" — nunca serve para acessar o serviço).
     */
    protected function isUsable(string $ip): bool
    {
        return ! Str::startsWith($ip, ['127.', '169.254.', '0.']);
    }

    /**
     * Menor pontuação primeiro. Adaptador virtual e faixa não-privada afundam.
     *
     * @param  array{ip: string, interface: string, origin?: string}  $address
     */
    protected function score(array $address): int
    {
        $score = 0;

        if (Str::contains(Str::lower($address['interface']), self::VIRTUAL_HINTS)) {
            $score += 100;
        }

        if (! $this->isPrivate($address['ip'])) {
            $score += 50;
        }

        // Endereço obtido por DHCP é quase sempre o da rede em uso; os fixos
        // costumam ser de adaptadores criados por outros programas.
        if (($address['origin'] ?? '') !== 'Dhcp') {
            $score += 10;
        }

        return $score;
    }

    protected function isPrivate(string $ip): bool
    {
        return filter_var(
            $ip,
            FILTER_VALIDATE_IP,
            FILTER_FLAG_IPV4 | FILTER_FLAG_NO_PRIV_RANGE,
        ) === false;
    }
}
