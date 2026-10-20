<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AuthenticationTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_cannot_access_web_or_protected_api_routes(): void
    {
        $this->get('/')
            ->assertRedirect('/login');

        $this->get('/register')
            ->assertNotFound();

        $this->post('/expenses', [])
            ->assertRedirect('/login');

        foreach ([
            'GET' => ['/api/v1/companies', '/api/v1/units', '/api/v1/expenses', '/api/v1/reports/units'],
        ] as $method => $paths) {
            foreach ($paths as $path) {
                $this->call($method, $path, [], [], [], ['HTTP_ACCEPT' => 'application/json'])
                    ->assertUnauthorized();
            }
        }

        $this->postJson('/api/v1/expenses/import')
            ->assertUnauthorized();

        $this->postJson('/api/v1/companies', ['name' => 'Não autorizado'])
            ->assertUnauthorized();
    }

    public function test_user_can_log_in_and_log_out_with_a_regenerated_session(): void
    {
        $user = User::factory()->create();

        $this->get('/login')
            ->assertOk()
            ->assertSee('Entrar');

        $this->post('/login', [
            'email' => $user->email,
            'password' => 'password',
        ])
            ->assertRedirect(route('dashboard'));

        $this->assertAuthenticatedAs($user);
        $this->get('/')
            ->assertOk()
            ->assertSee($user->name);

        $this->post('/logout')
            ->assertRedirect(route('login'));

        $this->assertGuest();
    }

    public function test_invalid_web_credentials_do_not_authenticate(): void
    {
        User::factory()->create();

        $this->from('/login')
            ->post('/login', [
                'email' => 'wrong@example.test',
                'password' => 'incorrect-password',
            ])
            ->assertRedirect('/login')
            ->assertSessionHasErrors('email');

        $this->assertGuest();
    }

    public function test_api_token_authenticates_requests_and_can_be_revoked(): void
    {
        $user = User::factory()->create();

        $response = $this->postJson('/api/v1/auth/token', [
            'email' => $user->email,
            'password' => 'password',
            'device_name' => 'test-client',
        ]);

        $response
            ->assertCreated()
            ->assertJsonPath('data.token_type', 'Bearer')
            ->assertJsonPath('data.user.id', $user->id);

        $token = $response->json('data.token');
        $this->assertIsString($token);
        $this->assertDatabaseCount('personal_access_tokens', 1);

        $this->withToken($token)
            ->getJson('/api/v1/expenses')
            ->assertOk();

        $this->withToken($token)
            ->deleteJson('/api/v1/auth/token')
            ->assertOk();

        $this->assertDatabaseCount('personal_access_tokens', 0);
        Auth::forgetGuards();

        $this->withToken($token)
            ->getJson('/api/v1/expenses')
            ->assertUnauthorized();
    }

    public function test_api_does_not_issue_tokens_for_invalid_credentials(): void
    {
        $user = User::factory()->create();

        $this->postJson('/api/v1/auth/token', [
            'email' => $user->email,
            'password' => 'incorrect-password',
            'device_name' => 'test-client',
        ])
            ->assertUnauthorized()
            ->assertJsonPath('message', 'As credenciais informadas são inválidas.');

        $this->assertDatabaseCount('personal_access_tokens', 0);
    }

    public function test_api_token_endpoint_limits_repeated_failed_logins(): void
    {
        $user = User::factory()->create();

        for ($attempt = 0; $attempt < 5; $attempt++) {
            $this->postJson('/api/v1/auth/token', [
                'email' => $user->email,
                'password' => 'incorrect-password',
                'device_name' => 'test-client',
            ])->assertUnauthorized();
        }

        $this->postJson('/api/v1/auth/token', [
            'email' => $user->email,
            'password' => 'incorrect-password',
            'device_name' => 'test-client',
        ])->assertTooManyRequests();

        $this->assertDatabaseCount('personal_access_tokens', 0);
    }

    public function test_admin_account_is_created_only_through_the_interactive_command(): void
    {
        $this->artisan('admin:create')
            ->expectsQuestion('Nome do administrador', 'Administradora')
            ->expectsQuestion('E-mail do administrador', 'ADMIN@example.test')
            ->expectsQuestion('Senha (mínimo de 12 caracteres)', 'Senha-segura-2026')
            ->expectsQuestion('Confirme a senha', 'Senha-segura-2026')
            ->expectsOutputToContain('Conta administrativa criada para admin@example.test.')
            ->assertExitCode(0);

        $user = User::query()->where('email', 'admin@example.test')->firstOrFail();

        $this->assertSame('Administradora', $user->name);
        $this->assertTrue(Hash::check('Senha-segura-2026', $user->password));
        $this->get('/register')->assertNotFound();
    }
}
