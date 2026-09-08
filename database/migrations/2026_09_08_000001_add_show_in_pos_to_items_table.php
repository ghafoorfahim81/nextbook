<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Whether an item is offered in the POS (point-of-sale) catalogue.
 *
 * A supermarket rings up almost everything at the till, so the field is shown
 * and defaulted on for that trade; a wholesaler or a services firm has no POS
 * at all and never sees it. Defaults to true so existing items keep showing
 * once the POS module lands.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('items', function (Blueprint $table) {
            $table->boolean('show_in_pos')->default(true)->index();
        });
    }

    public function down(): void
    {
        Schema::table('items', function (Blueprint $table) {
            $table->dropColumn('show_in_pos');
        });
    }
};
