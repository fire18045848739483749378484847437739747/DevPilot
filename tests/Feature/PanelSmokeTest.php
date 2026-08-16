<?php

namespace Tests\Feature;

use App\Enums\LogLevel;
use App\Enums\ServiceStatus;
use App\Filament\Pages\AgentPage;
use App\Filament\Pages\Dashboard;
use App\Filament\Pages\ProjectCommandsPage;
use App\Filament\Resources\ServiceGroups\Pages\CreateServiceGroup;
use App\Filament\Resources\ServiceGroups\Pages\EditServiceGroup;
use App\Filament\Resources\ServiceGroups\Pages\ListServiceGroups;
use App\Filament\Resources\Services\Pages\CreateService;
use App\Filament\Resources\Services\Pages\EditService;
use App\Filament\Resources\Services\Pages\ListServices;
use App\Filament\Resources\Services\Pages\ViewService;
use App\Filament\Widgets\PortMapWidget;
use App\Filament\Widgets\ResourceUsageChart;
use App\Filament\Widgets\ServiceControlWidget;
use App\Filament\Widgets\ServiceLogTableWidget;
use App\Filament\Widgets\ServiceStatusChart;
use App\Models\Service;
use App\Models\ServiceGroup;
use App\Models\ServiceLog;
use App\Models\ServiceMetric;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Renderiza todas as telas do painel. Serve para detectar uso incorreto da API
 * do Filament (que costuma falhar apenas em tempo de renderização).
 */
class PanelSmokeTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->actingAs(User::factory()->create());
    }

    public function test_dashboard_renders(): void
    {
        Livewire::test(Dashboard::class)->assertOk();
    }

    /**
     * Os gráficos se escondem quando não há serviços (canView), então o
     * dashboard vazio não os exercita. Este caso garante que eles renderizam.
     */
    public function test_dashboard_charts_render_with_data(): void
    {
        $service = Service::factory()->create(['port' => 8123]);

        ServiceMetric::query()->create([
            'service_id' => $service->getKey(),
            'cpu_usage' => 12.5,
            'memory_kb' => 65536,
            'port_open' => true,
            'recorded_at' => now()->subMinutes(2),
        ]);

        Livewire::test(Dashboard::class)->assertOk();
        Livewire::test(ResourceUsageChart::class)->assertOk();
        Livewire::test(ServiceStatusChart::class)->assertOk();
        Livewire::test(PortMapWidget::class)->assertOk();
    }

    public function test_control_widget_renders_with_quick_actions(): void
    {
        $service = Service::factory()->create(['name' => 'Meu Serviço']);

        Livewire::test(ServiceControlWidget::class)
            ->assertOk()
            ->assertSee('Meu Serviço')
            ->assertTableActionExists('start', record: $service)
            ->assertTableActionExists('stop', record: $service)
            ->assertTableActionExists('restart', record: $service);
    }

    public function test_dashboard_exposes_bulk_actions(): void
    {
        Livewire::test(Dashboard::class)
            ->assertActionExists('startAll')
            ->assertActionExists('stopAll')
            ->assertActionExists('restartAll');
    }

    public function test_list_page_renders(): void
    {
        Service::factory()->count(3)->create();

        Livewire::test(ListServices::class)->assertOk();
    }

    public function test_create_page_renders(): void
    {
        Livewire::test(CreateService::class)->assertOk();
    }

    public function test_view_page_renders(): void
    {
        $service = Service::factory()->create();

        Livewire::test(ViewService::class, ['record' => $service->getRouteKey()])->assertOk();
    }

    public function test_edit_page_renders(): void
    {
        $service = Service::factory()->create();

        Livewire::test(EditService::class, ['record' => $service->getRouteKey()])->assertOk();
    }

    /**
     * O modal dos atalhos só aparece quando a pasta é um projeto Laravel — e é
     * onde mora o schema do CheckboxList, que só quebra ao ser montado.
     */
    public function test_companion_shortcuts_action_mounts_for_laravel_projects(): void
    {
        $service = Service::factory()->create(['working_directory' => base_path()]);

        Livewire::test(ViewService::class, ['record' => $service->getRouteKey()])
            ->assertActionExists('companions')
            ->mountAction('companions')
            ->assertActionMounted('companions');
    }

    public function test_companion_shortcuts_action_is_hidden_outside_laravel_projects(): void
    {
        $service = Service::factory()->create(['working_directory' => storage_path('logs')]);

        Livewire::test(ViewService::class, ['record' => $service->getRouteKey()])
            ->assertActionHidden('companions');
    }

    public function test_log_history_widget_renders_with_filters(): void
    {
        $service = Service::factory()->create();

        $service->logs()->create([
            'type' => ServiceLog::TYPE_STDERR,
            'level' => LogLevel::Error,
            'message' => 'Erro de teste',
        ]);

        Livewire::test(ServiceLogTableWidget::class, ['record' => $service])
            ->assertOk()
            ->assertSee('Erro de teste')
            ->assertTableFilterExists('level')
            ->assertTableFilterExists('type');
    }

    public function test_group_pages_render(): void
    {
        $group = ServiceGroup::factory()->create();
        Service::factory()->create(['service_group_id' => $group->getKey()]);

        Livewire::test(ListServiceGroups::class)->assertOk();
        Livewire::test(CreateServiceGroup::class)->assertOk();
        Livewire::test(EditServiceGroup::class, ['record' => $group->getRouteKey()])->assertOk();
    }

    public function test_group_table_exposes_stack_actions(): void
    {
        $group = ServiceGroup::factory()->create();
        Service::factory()->create([
            'service_group_id' => $group->getKey(),
            'status' => ServiceStatus::Running,
            'pid' => 4242,
        ]);

        Livewire::test(ListServiceGroups::class)
            ->assertTableActionExists('startGroup', record: $group)
            ->assertTableActionExists('stopGroup', record: $group);
    }

    public function test_project_commands_page_renders_with_the_service_directory(): void
    {
        config(['services_manager.dev_roots' => [dirname(base_path())]]);

        Service::factory()->create(['working_directory' => base_path()]);

        Livewire::test(ProjectCommandsPage::class)
            ->assertOk()
            ->assertActionExists('chooseDirectory')
            ->assertActionExists('toggleDangerous')
            // Comando seguro aparece; destrutivo só depois do toggle.
            ->assertSee('php artisan optimize:clear')
            ->assertDontSee('php artisan migrate:fresh --force --seed')
            ->callAction('toggleDangerous')
            ->assertSee('php artisan migrate:fresh --force --seed');
    }

    /**
     * A página do agente conversa com o Windows (PowerShell) no mount; o teste
     * garante que a tela e as ações continuam de pé.
     */
    public function test_agent_page_renders(): void
    {
        Livewire::test(AgentPage::class)
            ->assertOk()
            ->assertActionExists('refresh');
    }

    /**
     * Garante que o tema e a sidebar recolhível continuam ligados no HTML
     * servido — são configurações fáceis de quebrar sem nenhum erro visível.
     */
    public function test_theme_and_collapsible_sidebar_are_wired(): void
    {
        Service::factory()->create();

        // O User não implementa FilamentUser, então o Filament só libera o
        // painel quando config('app.env') === 'local' — que é como a aplicação
        // realmente roda. Sem isto o teste receberia 403 por estar em `testing`.
        config(['app.env' => 'local']);

        $response = $this->get('/admin');

        $response->assertOk();
        $response->assertSee('css/hacker-theme.css', escape: false);
        // Marcador do Alpine que controla o recolhimento da sidebar.
        $response->assertSee('sidebar', escape: false);

        $panel = \Filament\Facades\Filament::getPanel('admin');

        $this->assertTrue($panel->isSidebarCollapsibleOnDesktop());
        $this->assertSame('dark', $panel->getDefaultThemeMode()->value);
    }
}
