<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use App\Enums\ItemType;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('items', function (Blueprint $table) {
            $table->enum('item_type', ItemType::values())->after('code')->nullable()->default(ItemType::INVENTORY_MATERIALS->value);
            $table->string('sku')->after('item_type')->nullable();
            $table->index(['item_type', 'sku']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('items', function (Blueprint $table) {
            $table->dropColumn('item_type');
            $table->dropColumn('sku');
            $table->dropIndex(['item_type', 'sku']);
        });
    }
};
