<?php

namespace Tests\Feature;

use App\Models\Service;
use App\Services\CommandRewriter;
use App\Services\NetworkAddresses;
use App\Services\PowerShell;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HostSelectionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        app(NetworkAddresses::class)->forget();
    }

    protected function tearDown(): void
    {
        app(NetworkAddresses::class)->forget();

        parent::tearDown();
    }

    public function test_host_is_replaced_in_the_command(): void
    {
        $this->assertSame(
            'php artisan serve --host=192.168.0.11 --port=8004',
            CommandRewriter::withHost('php artisan serve --host=127.0.0.1 --port=8004', '192.168.0.11'),
        );

        $this->assertSame(
            'php artisan serve --host 0.0.0.0 --port=8004',
            CommandRewriter::withHost('php artisan serve --host 127.0.0.1 --port=8004', '0.0.0.0'),
        );

        // A porta do `-S` precisa sobreviver à troca do host.
        $this->assertSame(
            'php -S 192.168.0.11:8080 -t public',
            CommandRewriter::withHost('php -S 127.0.0.1:8080 -t public', '192.168.0.11'),
        );
    }

    public function test_host_is_appended_to_a_serve_without_host(): void
    {
        $this->assertSame(
            'php artisan serve --port=8004 --host=192.168.0.11',
            CommandRewriter::withHost('php artisan serve --port=8004', '192.168.0.11'),
        );
    }

    /**
     * Não dá para adivinhar a sintaxe de host de um script qualquer; mexer no
     * comando às cegas quebraria o serviço.
     */
    public function test_unknown_commands_are_left_alone(): void
    {
        $this->assertSame(
            'node scripts/worker.js',
            CommandRewriter::withHost('node scripts/worker.js', '192.168.0.11'),
        );
    }

    public function test_host_is_extracted_without_masking_the_wildcard(): void
    {
        $this->assertSame('0.0.0.0', CommandRewriter::extractHost('php artisan serve --host=0.0.0.0'));
        $this->assertSame('192.168.0.11', CommandRewriter::extractHost('php -S 192.168.0.11:8080'));
        $this->assertNull(CommandRewriter::extractHost('npm run dev'));

        // O model continua mapeando 0.0.0.0 para um endereço clicável no link.
        $service = Service::factory()->create(['command' => 'php artisan serve --host=0.0.0.0 --port=8004']);
        $this->assertSame('127.0.0.1', $service->resolvedHost());
    }

    public function test_virtual_adapters_and_link_local_addresses_lose_to_the_real_network(): void
    {
        $this->fakePowerShell([
            'IP|172.25.64.1|vEthernet (WSL)|Manual',
            'IP|192.168.56.1|Ethernet 2|Manual',
            'IP|169.254.238.179|Conexão Local* 2|WellKnown',
            'IP|192.168.0.11|Wi-Fi|Dhcp',
            'IP|127.0.0.1|Loopback Pseudo-Interface 1|WellKnown',
        ]);

        $network = app(NetworkAddresses::class);

        $this->assertSame('192.168.0.11', $network->primary());

        $ips = array_column($network->addresses(), 'ip');

        $this->assertNotContains('127.0.0.1', $ips, 'loopback não é um IP de rede');
        $this->assertNotContains('169.254.238.179', $ips, 'link-local significa DHCP falhou');
        $this->assertSame(['192.168.0.11', '192.168.56.1', '172.25.64.1'], $ips);
    }

    public function test_options_always_offer_localhost_and_all_interfaces(): void
    {
        $this->fakePowerShell(['IP|192.168.0.11|Wi-Fi|Dhcp']);

        $options = app(NetworkAddresses::class)->options();

        $this->assertSame(
            [NetworkAddresses::LOCALHOST, '192.168.0.11', NetworkAddresses::ALL_INTERFACES],
            array_keys($options),
        );
    }

    /**
     * @param  list<string>  $lines
     */
    protected function fakePowerShell(array $lines): void
    {
        $this->app->bind(PowerShell::class, fn (): PowerShell => new class($lines) extends PowerShell
        {
            /**
             * @param  list<string>  $lines
             */
            public function __construct(protected array $lines)
            {
            }

            public function text(string $script, int $timeout = 60): string
            {
                return implode("\n", $this->lines);
            }
        });

        app(NetworkAddresses::class)->forget();
    }
}
