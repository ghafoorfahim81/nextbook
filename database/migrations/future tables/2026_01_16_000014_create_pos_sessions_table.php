<?php

use App\Enums\PosSessionStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::disableForeignKeyConstraints();

        Schema::create('pos_sessions', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->timestamp('opened_at');
            $table->timestamp('closed_at')->nullable();
            $table->enum('status', PosSessionStatus::values())
                ->default(PosSessionStatus::Open->value);
            $table->ulid('cashier_id')->index();
            $table->decimal('opening_cash', 19, 4)->default(0);
            $table->decimal('expected_cash', 19, 4)->default(0);
            $table->decimal('closing_cash', 19, 4)->default(0);
            $table->ulid('branch_id')->index();
            $table->ulid('created_by')->index();
            $table->ulid('updated_by')->nullable();
            $table->ulid('deleted_by')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::table('pos_sessions', function (Blueprint $table) {
            $table->foreign('cashier_id')->references('id')->on('users');
            $table->foreign('branch_id')->references('id')->on('branches');
            $table->foreign('created_by')->references('id')->on('users');
            $table->foreign('updated_by')->references('id')->on('users');
            $table->foreign('deleted_by')->references('id')->on('users')->onDelete('cascade');
        });

        Schema::enableForeignKeyConstraints();
    }

    public function down(): void
    {
        Schema::dropIfExists('pos_sessions');
    }
};
