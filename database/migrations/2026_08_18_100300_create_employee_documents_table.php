<?php

use App\Enums\EmployeeDocumentType;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Employee documents get their own table rather than living in `attachments`.
 *
 * `attachments` records only disk/path/original_name/mime_type/size. Compliance
 * needs a document type, a number, an issuing authority, an issue date, an
 * INDEXED expiry date and a verification state — none of which fit there.
 *
 * The FILE itself still uses the existing plumbing: EmployeeDocument uses the
 * HasAttachments trait and AttachmentService, so nothing about storage is
 * duplicated.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::disableForeignKeyConstraints();

        Schema::create('employee_documents', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->ulid('employee_id')->index();
            $table->enum('document_type', EmployeeDocumentType::values())
                ->default(EmployeeDocumentType::Other->value)
                ->index();

            $table->string('document_number')->nullable();
            $table->string('issued_by')->nullable();
            $table->date('issue_date')->nullable();
            // Indexed: the expiry reminder scans this daily across every branch.
            $table->date('expiry_date')->nullable()->index();

            $table->boolean('is_verified')->default(false);
            $table->ulid('verified_by')->nullable()->index();
            $table->timestamp('verified_at')->nullable();

            $table->smallInteger('reminder_days_before')->default(30);
            $table->timestamp('last_reminded_at')->nullable();
            $table->text('remark')->nullable();

            $table->ulid('branch_id')->index();
            $table->ulid('created_by')->index();
            $table->ulid('updated_by')->nullable();
            $table->ulid('deleted_by')->nullable();
            $table->softDeletes();
            $table->timestamps();

            $table->index(['branch_id', 'expiry_date']);
            $table->index(['branch_id', 'employee_id', 'document_type']);
        });

        Schema::enableForeignKeyConstraints();

        Schema::table('employee_documents', function (Blueprint $table) {
            $table->foreign('employee_id')->references('id')->on('employees')->onDelete('cascade');
            $table->foreign('verified_by')->references('id')->on('users')->onDelete('set null');
            $table->foreign('branch_id')->references('id')->on('branches');
            $table->foreign('created_by')->references('id')->on('users');
            $table->foreign('updated_by')->references('id')->on('users');
            $table->foreign('deleted_by')->references('id')->on('users')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('employee_documents');
    }
};
