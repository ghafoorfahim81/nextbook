<?php

namespace App\Http\Controllers\Administration;

use App\Http\Controllers\Controller;
use App\Models\Platform\SubscriptionInvoice;
use App\Models\Platform\SubscriptionPayment;
use App\Services\DateConversionService;
use App\Services\SubscriptionService;
use App\Support\CompanyContext;
use App\Support\Modules;
use Illuminate\Http\Request;

/**
 * What a company sees of its own subscription (phase 10): its package, until
 * when, how much of it is used, and its invoices and payments. Read only; the
 * packages themselves are managed outside the customer's reach.
 *
 * The subscription tables are not behind row level security, so everything
 * here is filtered by the current company explicitly.
 */
class MySubscriptionController extends Controller
{
    public function __construct(
        private readonly SubscriptionService $subscriptions,
        private readonly DateConversionService $dates,
    ) {
    }

    public function show(Request $request)
    {
        abort_unless($request->user()->can('companies.view'), 403);

        $company = CompanyContext::company();
        $subscription = $this->subscriptions->forCompany($company);
        $package = $subscription?->package;
        $usage = $this->subscriptions->usage($company);

        return inertia('Workspace/Subscription', [
            'plan' => $subscription ? [
                'package' => $package?->name,
                'description' => $package?->description,
                'state' => $subscription->state(),
                'starts_on' => $this->dates->toDisplay($subscription->starts_on->toDateString()),
                'ends_on' => $this->dates->toDisplay($subscription->ends_on->toDateString()),
                'days_left' => $subscription->daysLeft(),
                'block_reason' => $subscription->isBlocked() ? $subscription->block_reason : null,
                'max_users' => $package?->max_users,
                'max_branches' => $package?->max_branches,
                'modules' => $package?->modules,
            ] : null,
            'usage' => $usage,
            'availableModules' => Modules::available(),
            'invoices' => $subscription
                ? SubscriptionInvoice::query()->with('payments')->where('company_id', $company->id)
                    ->orderByDesc('issued_on')->orderByDesc('number')->get()
                    ->map(fn (SubscriptionInvoice $invoice) => [
                        'number' => $invoice->number,
                        'issued_on' => $this->dates->toDisplay($invoice->issued_on->toDateString()),
                        'due_on' => $invoice->due_on ? $this->dates->toDisplay($invoice->due_on->toDateString()) : null,
                        'amount' => (float) $invoice->amount,
                        'currency' => $invoice->currency,
                        'description' => $invoice->description,
                        'state' => $invoice->paymentState(),
                        'paid' => (float) $invoice->paidAmount(),
                        'balance' => (float) $invoice->balance(),
                        'payments' => $invoice->payments->map(fn (SubscriptionPayment $payment) => [
                            'amount' => (float) $payment->amount,
                            'paid_on' => $this->dates->toDisplay($payment->paid_on->toDateString()),
                            'method' => $payment->method,
                            'reference' => $payment->reference,
                        ])->values(),
                    ])
                : [],
        ]);
    }
}
