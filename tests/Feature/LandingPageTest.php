<?php

namespace Tests\Feature;

use Tests\TestCase;

/**
 * A página inicial pública é montada a partir de `services_manager.project`.
 * Como é Blade puro, um erro nela só aparece em tempo de renderização.
 */
class LandingPageTest extends TestCase
{
    public function test_the_landing_page_renders(): void
    {
        $this->get('/')->assertOk();
    }

    public function test_it_shows_the_project_identity_from_the_config(): void
    {
        config([
            'services_manager.project.name' => 'DevPilot',
            'services_manager.project.repository' => 'https://github.com/EudesSA/DevPilot',
        ]);

        $this->get('/')
            ->assertOk()
            ->assertSee('DevPilot')
            ->assertSee('https://github.com/EudesSA/DevPilot', escape: false);
    }

    /**
     * O README e a landing page envelheceram juntos uma vez; este teste falha
     * se as seções dos recursos mais recentes sumirem da página.
     */
    public function test_it_documents_the_current_feature_set(): void
    {
        $response = $this->get('/')->assertOk();

        $response->assertSee('php artisan agent:install', escape: false);
        $response->assertSee('Grupos de serviços', escape: false);
        $response->assertSee('Health check HTTP', escape: false);
        $response->assertSee('Comandos do projeto', escape: false);
    }

    public function test_the_links_without_a_configured_repository_are_omitted(): void
    {
        config(['services_manager.project.repository' => '']);

        $this->get('/')
            ->assertOk()
            ->assertDontSee('Ver no GitHub');
    }
}
