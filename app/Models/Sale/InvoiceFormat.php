<?php

namespace App\Models\Sale;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Models\Administration\Company;

class InvoiceFormat extends Model
{
    use HasUlids, SoftDeletes;

    protected $fillable = [
        'company_id',
        'name',
        'is_default',
        'paper_size',
        'paper_orientation',
        'margins',
        'language',
        'direction',
        'header_config',
        'item_columns',
        'optional_sections',
        'appearance',
        'watermark_text',
        'footer_text',
        'bank_details',
        'thank_you_text',
        'custom_css',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'is_default'       => 'boolean',
            'margins'          => 'array',
            'header_config'    => 'array',
            'item_columns'     => 'array',
            'optional_sections'=> 'array',
            'appearance'       => 'array',
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public static function defaultConfig(): array
    {
        return [
            'paper_size'        => 'a4',
            'paper_orientation' => 'portrait',
            'language'          => 'en',
            'direction'         => 'ltr',
            'margins'           => ['top' => 10, 'right' => 10, 'bottom' => 10, 'left' => 10],
            'header_config'     => [
                'show_logo'             => true,
                'show_company_name'     => true,
                'show_company_address'  => true,
                'show_company_phone'    => true,
                'show_invoice_number'   => true,
                'show_date'             => true,
                'show_due_date'         => true,
                'show_customer_name'    => true,
                'show_customer_address' => false,
                'show_customer_phone'   => false,
                'show_store_name'       => false,
                'logo_max_height'       => 64,
                'title_text'            => 'INVOICE',
            ],
            'item_columns'      => [
                'visible'           => ['row', 'name', 'quantity', 'unit_price', 'discount', 'total'],
                'column_labels'     => [
                    'row'        => '#',
                    'code'       => 'Code',
                    'name'       => 'Item',
                    'unit'       => 'Unit',
                    'quantity'   => 'Qty',
                    'unit_price' => 'Rate',
                    'discount'   => 'Discount',
                    'tax'        => 'Tax',
                    'total'      => 'Amount',
                ],
                'header_bg_color'   => '#1e293b',
                'header_text_color' => '#ffffff',
                'header_font_size'  => 13,
                'row_font_size'     => 13,
                'stripe_rows'       => false,
                'stripe_color'      => '#f8fafc',
                'show_borders'      => true,
            ],
            'optional_sections' => [
                'show_notes'              => true,
                'show_terms'              => true,
                'show_footer'             => false,
                'show_signature'          => false,
                'show_qr'                 => false,
                'show_barcode'            => false,
                'show_bank_details'       => false,
                'show_customer_tax_number'=> false,
                'show_thank_you'          => false,
                'show_summary_subtotal'   => true,
                'show_summary_discount'   => true,
                'show_summary_tax'        => false,
                'items_per_page'          => 0,
                'tax_display'             => 'per_item',
            ],
            'appearance'        => [
                'bg_color'          => '#ffffff',
                'font_family'       => 'sans-serif',
                'font_size'         => 14,
                'font_color'        => '#0f172a',
                'border_show'       => true,
                'border_color'      => '#cbd5e1',
                'border_width'      => 1,
                'summary_bg_color'  => '#f1f5f9',
                'summary_text_color'=> '#0f172a',
            ],
        ];
    }
}
