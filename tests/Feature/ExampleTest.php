<?php

namespace Tests\Feature;

// use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExampleTest extends TestCase
{
    public function test_the_dashboard_redirects_guests_to_login(): void
    {
        $this->get('/')
            ->assertRedirect('/login');
    }

    public function test_versioned_api_health_endpoint_returns_json(): void
    {
        $this->getJson('/api/v1/health')
            ->assertOk()
            ->assertExactJson(['status' => 'ok']);
    }
}
