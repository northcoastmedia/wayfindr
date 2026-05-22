<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\Site;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AgentAuthenticationTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_is_redirected_from_dashboard_to_login(): void
    {
        $this->get('/dashboard')
            ->assertRedirect('/login');
    }

    public function test_login_form_renders(): void
    {
        $this->get('/login')
            ->assertOk()
            ->assertSee('Agent Login');
    }

    public function test_agent_can_log_in_and_view_account_scoped_dashboard(): void
    {
        $account = Account::factory()->create(['name' => 'Acme Support']);
        $otherAccount = Account::factory()->create(['name' => 'Other Support']);

        $agent = User::factory()->for($account)->create([
            'email' => 'agent@example.com',
            'password' => Hash::make('password'),
        ]);

        Site::factory()->for($account)->create(['name' => 'Acme Help']);
        Site::factory()->for($otherAccount)->create(['name' => 'Other Help']);

        $this->post('/login', [
            'email' => 'agent@example.com',
            'password' => 'password',
        ])
            ->assertRedirect('/dashboard');

        $this->assertAuthenticatedAs($agent);

        $this->get('/dashboard')
            ->assertOk()
            ->assertSee('Acme Support')
            ->assertSee('Acme Help')
            ->assertDontSee('Other Help');
    }

    public function test_logout_ends_the_agent_session(): void
    {
        $agent = User::factory()->for(Account::factory())->create();

        $this->actingAs($agent)
            ->post('/logout')
            ->assertRedirect('/login');

        $this->assertGuest();
    }

    public function test_database_seeder_creates_demo_account_agent_and_site(): void
    {
        $this->seed(DatabaseSeeder::class);

        $agent = User::query()->where('email', 'agent@example.com')->firstOrFail();

        $this->assertSame('Demo Support Co', $agent->account->name);
        $this->assertTrue(Hash::check('password', $agent->password));
        $this->assertDatabaseHas('sites', [
            'account_id' => $agent->account_id,
            'name' => 'Demo Site',
            'domain' => 'demo.test',
        ]);
    }
}
