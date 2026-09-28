<?php

namespace App\Services\Accounting;

use App\Enums\LedgerType;
use App\Models\Ledger\Ledger;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Pairing the two accounts of one person.
 *
 * A party who both buys from us and sells to us has a customer ledger and a
 * supplier ledger, because a ledger's control account follows its type. The
 * pairing is the only thing in the data that says they are the same person —
 * it drives the combined statement, and it is what lets the set-off form know
 * which supplier goes with which customer.
 *
 * Every write goes through here rather than through a mass-assigned column,
 * because the link is symmetric and there is no way to set one side correctly
 * on its own. Setting A→B while B still points at C leaves a pairing that is
 * true read from A and false read from B, and nothing would catch it: both
 * statements render perfectly well on their own.
 */
class LedgerLinkService
{
    /**
     * Pair two ledgers, replacing whatever either was paired with before.
     *
     * Idempotent: re-linking an existing pair is a no-op rather than an error,
     * so a form that resubmits the same value does not have to know.
     */
    public function link(Ledger $ledger, Ledger $counterpart): void
    {
        $this->assertPairable($ledger, $counterpart);

        if ($ledger->counterpart_ledger_id === $counterpart->id
            && $counterpart->counterpart_ledger_id === $ledger->id) {
            return;
        }

        DB::transaction(function () use ($ledger, $counterpart) {
            // Whoever they were paired with before is now unpaired. Left in
            // place, that third ledger would still point here and claim a
            // partner that has moved on.
            $this->releasePartnerOf($ledger);
            $this->releasePartnerOf($counterpart);

            $this->write($ledger, $counterpart->id);
            $this->write($counterpart, $ledger->id);
        });
    }

    /** Break the pairing from either side. */
    public function unlink(Ledger $ledger): void
    {
        if (! $ledger->counterpart_ledger_id) {
            return;
        }

        DB::transaction(function () use ($ledger) {
            $this->releasePartnerOf($ledger);
            $this->write($ledger, null);
        });
    }

    /**
     * Apply what a form submitted: an id pairs, an empty value unpairs, and a
     * missing key leaves the pairing alone.
     *
     * The three cases are distinct on purpose. A form that does not render the
     * field at all must not silently break a pairing someone set elsewhere.
     */
    public function sync(Ledger $ledger, array $validated, string $key = 'counterpart_ledger_id'): void
    {
        if (! array_key_exists($key, $validated)) {
            return;
        }

        $wanted = $validated[$key] ?: null;

        if (! $wanted) {
            $this->unlink($ledger);

            return;
        }

        $this->link($ledger, Ledger::findOrFail($wanted));
    }

    /**
     * The two balances of one person, side by side, and what is left after a
     * set-off.
     *
     * Returned as plain numbers rather than a formatted statement because both
     * the party page and the set-off form need them, and they present them
     * differently.
     *
     * @return array{
     *     receivable: float, payable: float, offsettable: float,
     *     net: float, net_side: string
     * }|null
     */
    public function combinedPosition(Ledger $ledger): ?array
    {
        $counterpart = $ledger->counterpart;

        if (! $counterpart) {
            return null;
        }

        [$customer, $supplier] = $this->typeOf($ledger) === LedgerType::CUSTOMER->value
            ? [$ledger, $counterpart]
            : [$counterpart, $ledger];

        // A customer balance is a debit and a supplier balance a credit, so
        // both are reported as positive amounts owed in their own direction.
        $receivable = max(0.0, $this->balanceOf($customer));
        $payable = max(0.0, -$this->balanceOf($supplier));

        $net = $receivable - $payable;

        return [
            'receivable' => round($receivable, 4),
            'payable' => round($payable, 4),
            // Only the overlap can be set off. Beyond it there is nothing to
            // cancel against, and the settlement engine would park the excess
            // as an advance nobody agreed to.
            'offsettable' => round(min($receivable, $payable), 4),
            'net' => round(abs($net), 4),
            'net_side' => $net >= 0 ? 'receivable' : 'payable',
        ];
    }

    private function assertPairable(Ledger $ledger, Ledger $counterpart): void
    {
        if ($ledger->id === $counterpart->id) {
            throw ValidationException::withMessages([
                'counterpart_ledger_id' => [__('general.counterpart_cannot_be_itself')],
            ]);
        }

        // One customer and one supplier, in either order. This also rejects
        // two ledgers of the same role and anything involving an employee.
        $roles = [$this->typeOf($ledger), $this->typeOf($counterpart)];
        sort($roles);

        if ($roles !== [LedgerType::CUSTOMER->value, LedgerType::SUPPLIER->value]) {
            throw ValidationException::withMessages([
                'counterpart_ledger_id' => [__('general.counterpart_must_be_the_other_role')],
            ]);
        }

        if ((string) $ledger->branch_id !== (string) $counterpart->branch_id) {
            throw ValidationException::withMessages([
                'counterpart_ledger_id' => [__('general.counterpart_must_share_a_branch')],
            ]);
        }
    }

    /** Clear the pointer on whoever this ledger is currently paired with. */
    private function releasePartnerOf(Ledger $ledger): void
    {
        $partnerId = $ledger->counterpart_ledger_id;

        if (! $partnerId) {
            return;
        }

        $partner = Ledger::withoutGlobalScopes()->find($partnerId);

        if ($partner) {
            $this->write($partner, null);
        }
    }

    /**
     * Written with a query rather than save() so a ledger whose other columns
     * are mid-edit does not get them flushed as a side effect of pairing.
     */
    private function write(Ledger $ledger, ?string $counterpartId): void
    {
        Ledger::withoutGlobalScopes()
            ->whereKey($ledger->id)
            ->update(['counterpart_ledger_id' => $counterpartId]);

        $ledger->counterpart_ledger_id = $counterpartId;
        $ledger->syncOriginalAttribute('counterpart_ledger_id');
        $ledger->unsetRelation('counterpart');
    }

    private function typeOf(Ledger $ledger): string
    {
        return (string) ($ledger->type?->value ?? $ledger->type);
    }

    /** Debit minus credit, in the home currency. */
    private function balanceOf(Ledger $ledger): float
    {
        return (float) DB::table('transaction_lines as tl')
            ->join('transactions as t', 't.id', '=', 'tl.transaction_id')
            ->where('tl.ledger_id', $ledger->id)
            ->whereIn('t.status', ['posted', 'reversed'])
            ->whereNull('tl.deleted_at')
            ->whereNull('t.deleted_at')
            ->selectRaw('COALESCE(SUM(tl.base_debit - tl.base_credit), 0) AS b')
            ->value('b');
    }
}
