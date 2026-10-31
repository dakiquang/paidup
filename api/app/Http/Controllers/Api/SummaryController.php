<?php

namespace App\Http\Controllers\Api;

use App\Enums\InvoiceStatus;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SummaryController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $invoices = $request->user()->invoices();

        $totals = fn ($query) => $query->selectRaw('currency, sum(amount_cents) as cents, count(*) as count')
            ->groupBy('currency')
            ->get()
            ->map(fn ($row) => ['currency' => $row->currency, 'cents' => (int) $row->cents, 'count' => (int) $row->count]);

        return response()->json([
            'outstanding' => $totals((clone $invoices)->outstanding()),
            'overdue' => $totals((clone $invoices)->outstanding()->whereDate('due_on', '<', today())),
            'paid_this_month' => $totals(
                (clone $invoices)->where('status', InvoiceStatus::Paid)->where('paid_at', '>=', now()->startOfMonth()),
            ),
        ]);
    }
}
