<?php

namespace Database\Seeders;

use App\Enums\InvoiceStatus;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\User;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $user = User::factory()->create(['name' => 'Demo Studio', 'email' => 'demo@paidup.test']);
        $customers = Customer::factory(3)->for($user)->create();

        $rows = [
            ['Website retainer', 180000, InvoiceStatus::Open, now()->addDays(10)],
            ['Support plan', 45000, InvoiceStatus::Open, now()->subDays(6)],
            ['Design sprint', 320000, InvoiceStatus::Paid, now()->subDays(12)],
            ['Hosting', 9900, InvoiceStatus::Draft, now()->addDays(20)],
        ];

        foreach ($rows as $i => [$description, $cents, $status, $due]) {
            Invoice::factory()->create([
                'user_id' => $user->id,
                'customer_id' => $customers[$i % 3]->id,
                'number' => sprintf('INV-%04d', $i + 1),
                'description' => $description,
                'amount_cents' => $cents,
                'status' => $status,
                'due_on' => $due,
                'paid_at' => $status === InvoiceStatus::Paid ? now() : null,
            ]);
        }
    }
}
