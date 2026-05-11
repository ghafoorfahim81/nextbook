<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('invoice_formats', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->ulid('company_id')->index();
            $table->foreign('company_id')->references('id')->on('companies')->cascadeOnDelete();

            $table->string('name');
            $table->boolean('is_default')->default(false);

            // Paper settings
            $table->string('paper_size')->default('a4');         // a4, a5, letter, thermal_80mm
            $table->string('paper_orientation')->default('portrait'); // portrait, landscape
            $table->json('margins')->nullable();                  // {top, right, bottom, left} in mm

            // Language & direction
            $table->string('language')->default('en');           // en, fa, ps
            $table->string('direction')->default('ltr');          // ltr, rtl

            // Header configuration
            $table->json('header_config')->nullable();
            // {
            //   show_logo, show_company_name, show_company_address, show_company_phone,
            //   show_invoice_number, show_date, show_due_date, show_customer_name,
            //   show_customer_address, show_customer_phone, show_store_name,
            //   logo_max_height (px), title_text
            // }

            // Item table
            $table->json('item_columns')->nullable();
            // {
            //   visible: [row, code, name, unit, quantity, unit_price, discount, tax, total],
            //   header_bg_color, header_text_color, header_font_size, row_font_size,
            //   stripe_rows, show_borders
            // }

            // Optional sections toggles
            $table->json('optional_sections')->nullable();
            // {
            //   show_notes, show_terms, show_footer, show_signature,
            //   show_qr, show_barcode, show_bank_details,
            //   show_customer_tax_number, show_thank_you,
            //   show_summary_subtotal, show_summary_discount, show_summary_tax,
            //   items_per_page (0 = auto), tax_display (per_item | grouped)
            // }

            // Visual appearance
            $table->json('appearance')->nullable();
            // {
            //   bg_color, font_family, font_size, font_color,
            //   border_show, border_color, border_width,
            //   summary_bg_color, summary_text_color,
            //   header_bg_color (page header), header_text_color
            // }

            // Watermark
            $table->string('watermark_text')->nullable();        // e.g. "PAID", "COPY"

            // Footer & other text blocks
            $table->text('footer_text')->nullable();
            $table->text('bank_details')->nullable();
            $table->text('thank_you_text')->nullable();

            // Advanced
            $table->text('custom_css')->nullable();

            $table->ulid('created_by');
            $table->ulid('updated_by')->nullable();
            $table->softDeletes();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('invoice_formats');
    }
};
