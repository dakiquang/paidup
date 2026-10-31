<?php

namespace App\Http\Controllers\Api;

use App\Enums\InvoiceStatus;
use App\Exceptions\InvoiceStateConflict;
use App\Http\Controllers\Controller;
use App\Http\Resources\InvoiceResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum;

class InvoiceController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $request->validate(['status' => ['nullable', new Enum(InvoiceStatus::class)]]);

        $invoices = $request->user()->invoices()
            ->with('customer')
            ->when($request->query('status'), fn ($query, $status) => $query->where('status', $status))
            ->orderByDesc('due_on')
            ->orderByDesc('id')
            ->paginate(50);

        return InvoiceResource::collection($invoices);
    }

    public function store(Request $request): InvoiceResource
    {
        $user = $request->user();

        $data = $request->validate([
            'customer_id' => ['required', 'integer', Rule::exists('customers', 'id')->where('user_id', $user->id)],
            'description' => ['required', 'string', 'max:190'],
            // Stripe's minimum charge, and a ceiling that rules out a typo.
            'amount_cents' => ['required', 'integer', 'min:50', 'max:99999999'],
            'currency' => ['required', Rule::in(['aud', 'eur', 'nzd', 'usd'])],
            'due_on' => ['required', 'date', 'after_or_equal:today'],
        ]);

        $invoice = DB::transaction(function () use ($user, $data) {
            $user->newQuery()->whereKey($user->id)->lockForUpdate()->first();
            $next = $user->invoices()->count() + 1;

            return $user->invoices()->create([...$data, 'number' => sprintf('INV-%04d', $next)]);
        });

        return new InvoiceResource($invoice->load('customer'));
    }

    public function show(Request $request, int $invoice): InvoiceResource
    {
        return new InvoiceResource($request->user()->invoices()->with('customer')->findOrFail($invoice));
    }

    public function send(Request $request, int $invoice): InvoiceResource
    {
        return $this->move($request, $invoice, [InvoiceStatus::Draft], InvoiceStatus::Open);
    }

    public function void(Request $request, int $invoice): InvoiceResource
    {
        return $this->move($request, $invoice, [InvoiceStatus::Draft, InvoiceStatus::Open], InvoiceStatus::Void);
    }

    /** @param  list<InvoiceStatus>  $from */
    private function move(Request $request, int $id, array $from, InvoiceStatus $to): InvoiceResource
    {
        $invoice = DB::transaction(function () use ($request, $id, $from, $to) {
            $invoice = $request->user()->invoices()->lockForUpdate()->findOrFail($id);

            if (! in_array($invoice->status, $from, true)) {
                throw new InvoiceStateConflict("A {$invoice->status->value} invoice can't become {$to->value}.");
            }

            $invoice->update(['status' => $to]);

            return $invoice;
        });

        return new InvoiceResource($invoice->load('customer'));
    }
}
