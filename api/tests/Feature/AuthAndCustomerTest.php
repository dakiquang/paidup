<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\PersonalAccessToken;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AuthAndCustomerTest extends TestCase
{
    use RefreshDatabase;

    private function login(string $password = 'password')
    {
        return $this->postJson('/api/login', [
            'email' => 'owner@example.com',
            'password' => $password,
            'device_name' => 'test',
        ]);
    }

    public function test_login_returns_a_token_that_works_and_logout_revokes_it(): void
    {
        User::factory()->create(['email' => 'owner@example.com']);

        $token = $this->login()->assertOk()->json('token');

        $this->withToken($token)->getJson('/api/summary')->assertOk();
        $this->withToken($token)->postJson('/api/logout')->assertNoContent();
        $this->assertSame(0, PersonalAccessToken::count());
    }

    public function test_wrong_password_is_refused(): void
    {
        User::factory()->create(['email' => 'owner@example.com']);

        $this->login('wrong')->assertJsonValidationErrors('email');
    }

    public function test_login_is_rate_limited(): void
    {
        foreach (range(1, 5) as $ignored) {
            $this->login('wrong');
        }

        $this->login('wrong')->assertTooManyRequests();
    }

    public function test_the_api_needs_a_token(): void
    {
        $this->getJson('/api/invoices')->assertUnauthorized();
        $this->get('/api/invoices')->assertUnauthorized();
    }

    public function test_customers_belong_to_one_business(): void
    {
        $user = User::factory()->create();
        Customer::factory()->create();
        Sanctum::actingAs($user);

        $this->postJson('/api/customers', ['name' => 'Acme', 'email' => 'ap@acme.test'])->assertCreated();
        $this->postJson('/api/customers', ['name' => 'Acme', 'email' => 'ap@acme.test'])->assertJsonValidationErrors('email');

        $this->getJson('/api/customers')->assertJsonCount(1, 'data')->assertJsonPath('data.0.name', 'Acme');
    }

    public function test_two_businesses_can_share_a_customer_email(): void
    {
        Customer::factory()->create(['email' => 'ap@acme.test']);
        Sanctum::actingAs(User::factory()->create());

        $this->postJson('/api/customers', ['name' => 'Acme', 'email' => 'ap@acme.test'])->assertCreated();
    }
}
