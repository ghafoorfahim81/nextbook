<?php

use App\Enums\TransactionStatus;
use App\Models\Purchase\Purchase;
use App\Models\Sale\Sale;
use App\Services\StockService;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('stock_balances', function (Blueprint $table) {
            $table->decimal('reserved_out', 18, 4)->default(0)->after('quantity');
            $table->decimal('reserved_in', 18, 4)->default(0)->after('reserved_out');
        });

        $this->backfillExistingDrafts();
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('stock_balances', function (Blueprint $table) {
            $table->dropColumn(['reserved_out', 'reserved_in']);
        });
    }

    /**
     * Replay reservations for documents that are still drafts so their reserved
     * quantities are correct immediately after rollout. Reuses StockService::reserve()
     * to keep unit-conversion identical to the live lifecycle hooks.
     */
    private function backfillExistingDrafts(): void
    {
        $stockService = app(StockService::class);

        $documents = collect()
            ->merge(Sale::query()->where('status', TransactionStatus::DRAFT->value)->with('transaction')->get())
            ->merge(Purchase::query()->where('status', TransactionStatus::DRAFT->value)->with('transaction')->get());

        foreach ($documents as $document) {
            $payloads = (array) data_get($document->transaction?->posting_payload, 'stock_movements', []);

            foreach ($payloads as $payload) {
                if (empty($payload['item_id'])) {
                    continue;
                }

                $stockService->reserve($payload);
            }
        }
    }
};
