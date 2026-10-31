<?php

namespace Tests\Feature;

use App\Enums\InvoiceStatus;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class InvoiceTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Customer $customer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $this->customer = Customer::factory()->for($this->user)->create();
        Sanctum::actingAs($this->user);
    }

    private function invoice(array $attributes = []): Invoice
    {
        return Invoice::factory()->create(['customer_id' => $this->customer->id, ...$attributes]);
    }

    private function payload(array $overrides = []): array
    {
        return [
            'customer_id' => $this->customer->id,
            'description' => 'Monthly retainer',
            'amount_cents' => 50000,
            'currency' => 'aud',
            'due_on' => today()->addWeek()->toDateString(),
            ...$overrides,
        ];
    }

    public function test_a_new_invoice_is_a_numbered_draft(): void
    {
        $this->postJson('/api/invoices', $this->payload())
            ->assertCreated()
            ->assertJsonPath('data.number', 'INV-0001')
            ->assertJsonPath('data.status', 'draft')
            ->assertJsonPath('data.customer.id', $this->customer->id);

        $this->postJson('/api/invoices', $this->payload())->assertJsonPath('data.number', 'INV-0002');
    }

    #[DataProvider('invalid')]
    public function test_bad_input_is_refused(array $overrides, string $field): void
    {
        $this->postJson('/api/invoices', $this->payload($overrides))->assertJsonValidationErrors($field);
    }

    public static function invalid(): array
    {
        return [
            'below the minimum charge' => [['amount_cents' => 49], 'amount_cents'],
            'fractional cents' => [['amount_cents' => 10.5], 'amount_cents'],
            'unsupported currency' => [['currency' => 'jpy'], 'currency'],
            'due in the past' => [['due_on' => '2020-01-01'], 'due_on'],
            'no description' => [['description' => ''], 'description'],
        ];
    }

    public function test_another_businesss_customer_is_refused(): void
    {
        $other = Customer::factory()->create();

        $this->postJson('/api/invoices', $this->payload(['customer_id' => $other->id]))
            ->assertJsonValidationErrors('customer_id');
    }

    public function test_the_list_shows_only_my_invoices_and_filters_by_status(): void
    {
        $this->invoice(['status' => InvoiceStatus::Open]);
        $this->invoice(['status' => InvoiceStatus::Paid]);
        Invoice::factory()->create();

        $this->getJson('/api/invoices')->assertOk()->assertJsonCount(2, 'data');
        $this->getJson('/api/invoices?status=paid')->assertJsonCount(1, 'data');
        $this->getJson('/api/invoices?status=nonsense')->assertJsonValidationErrors('status');
    }

    public function test_a_single_invoice_is_scoped_to_its_owner(): void
    {
        $this->getJson('/api/invoices/'.$this->invoice()->id)->assertOk();
        $this->getJson('/api/invoices/'.Invoice::factory()->create()->id)->assertNotFound();
    }

    public function test_overdue_is_flagged_only_while_money_is_owed(): void
    {
        $late = $this->invoice(['due_on' => today()->subDay()]);
        $paid = $this->invoice(['due_on' => today()->subDay(), 'status' => InvoiceStatus::Paid]);
        $today = $this->invoice(['due_on' => today()]);

        $this->assertTrue($late->isOverdue());
        $this->assertFalse($paid->isOverdue());
        $this->assertFalse($today->isOverdue());
    }

    public function test_sending_and_voiding_follow_the_lifecycle(): void
    {
        $draft = $this->invoice(['status' => InvoiceStatus::Draft]);

        $this->postJson("/api/invoices/{$draft->id}/send")->assertJsonPath('data.status', 'open');
        $this->postJson("/api/invoices/{$draft->id}/send")->assertConflict();
        $this->postJson("/api/invoices/{$draft->id}/void")->assertJsonPath('data.status', 'void');
    }

    #[DataProvider('locked')]
    public function test_money_in_motion_cannot_be_voided(InvoiceStatus $status): void
    {
        $invoice = $this->invoice(['status' => $status]);

        $this->postJson("/api/invoices/{$invoice->id}/void")->assertConflict();
        $this->assertSame($status, $invoice->fresh()->status);
    }

    public static function locked(): array
    {
        return [[InvoiceStatus::Processing], [InvoiceStatus::Paid]];
    }

    public function test_the_summary_totals_by_currency(): void
    {
        $this->invoice(['amount_cents' => 1000]);
        $this->invoice(['amount_cents' => 2000, 'due_on' => today()->subDay()]);
        $this->invoice(['amount_cents' => 700, 'currency' => 'usd']);
        $this->invoice(['amount_cents' => 900, 'status' => InvoiceStatus::Paid, 'paid_at' => now()]);
        $this->invoice(['amount_cents' => 800, 'status' => InvoiceStatus::Paid, 'paid_at' => now()->subMonths(2)]);
        Invoice::factory()->create(['amount_cents' => 5000]);

        $this->getJson('/api/summary')
            ->assertOk()
            ->assertJsonFragment(['currency' => 'aud', 'cents' => 3000, 'count' => 2])
            ->assertJsonFragment(['currency' => 'usd', 'cents' => 700, 'count' => 1])
            ->assertJsonPath('overdue.0.cents', 2000)
            ->assertJsonPath('paid_this_month.0.cents', 900)
            ->assertJsonCount(1, 'paid_this_month');
    }
}
