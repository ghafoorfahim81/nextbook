<?php

use App\Enums\BusinessType;

/**
 * How each trade shapes the item module.
 *
 * A business type is a *combination of flags*, not a different schema. Every
 * profile below writes to the same tables — it only decides which fields the
 * form shows, which tracking defaults are pre-set, which sections (variants,
 * lots, pieces) appear, and what the lot identifier is called.
 *
 * Adding a new trade means adding a key here. It must never mean a migration.
 *
 * `base` is merged under every profile, so a profile lists only what differs.
 * Company preferences are merged over the result, so an owner can still switch
 * any individual field on or off.
 *
 * EVERY toggleable field must appear in `base.fields`. A field missing from
 * that list can never be shown, because the form reads its visibility from
 * this map.
 */
return [

    'base' => [
        // What the lot identifier is called in this trade.
        'spec_label' => 'batch',

        'sections' => [
            'variants' => false,   // size/colour/RAM — own SKU, barcode, price
            'lots'     => false,   // batch / expiry
            'pieces'   => false,   // serial / IMEI / individually measured
            'openings' => true,
            'accounts' => true,
        ],

        // Initial values for the tracking flags on a new item.
        'defaults' => [
            'is_batch_tracked'  => false,
            'is_expiry_tracked' => false,
            'is_serial_tracked' => false,
            'is_color_tracked'  => false,
            'is_size_tracked'   => false,
            'is_weighted'       => false,
            'has_variants'      => false,
        ],

        'fields' => [
            // --- Identity ---------------------------------------------------
            'code'         => true,
            'sku'          => true,
            'barcode'      => true,
            'item_type'    => true,
            'generic_name' => false,
            'packing'      => true,
            'description'  => true,
            'photo'        => true,
            'model'        => false,

            // --- Classification ----------------------------------------------
            // items.colors and items.size_id are superseded by item_variants
            // and are not rendered as item-level inputs. The is_color_tracked
            // / is_size_tracked toggles below still drive the opening rows.
            'category'     => true,
            'brand'        => true,
            'unit_measure' => true,

            // --- Pricing -------------------------------------------------------
            'purchase_price'    => true,
            'cost'              => true,
            'sale_price'        => true,
            'margin_percentage' => true,
            'rate_a'            => false,
            'rate_b'            => false,
            'rate_c'            => false,
            'pricing_method'    => false,
            'costing_method'    => false,

            // --- Stock control ----------------------------------------------
            'minimum_stock'     => true,
            'maximum_stock'     => true,
            'reorder_quantity'  => false,
            'lead_time_days'    => false,
            'rack_no'           => false,
            'fast_search'       => true,
            'default_warehouse' => false,

            // --- Physical -----------------------------------------------------
            'weight'     => false,
            'dimensions' => false,

            // --- Sourcing & compliance ------------------------------------------
            'manufacturer'           => false,
            'country_of_origin'      => false,
            'hs_code'                => false,
            'warranty_months'        => false,
            'shelf_life_days'        => false,
            'min_shelf_life_percent' => false,
            'storage_zone'           => false,
            'requires_prescription'  => false,
            'is_controlled'          => false,

            // --- Behaviour -----------------------------------------------------
            'is_active'            => true,
            'is_stockable'         => false,
            'is_sellable'          => false,
            'is_purchasable'       => false,
            'is_weighted'          => false,
            'allow_negative_stock' => false,

            // --- Tracking toggles ------------------------------------------------
            'is_batch_tracked'  => false,
            'is_expiry_tracked' => false,
            'is_color_tracked'  => false,
            'is_size_tracked'   => false,
            'is_serial_tracked' => false,

            // --- Accounting ------------------------------------------------------
            'accounts' => true,
        ],
    ],

    'profiles' => [

        // Batch + expiry, prescription control, generic names.
        BusinessType::PHARMACY->value => [
            'spec_label' => 'batch',
            'sections' => ['variants' => true, 'lots' => true],
            'defaults' => ['is_batch_tracked' => true, 'is_expiry_tracked' => true],
            'fields' => [
                'generic_name'           => true,
                'manufacturer'           => true,
                'model'                  => false,
                'shelf_life_days'        => true,
                'min_shelf_life_percent' => true,
                'storage_zone'           => true,
                'requires_prescription'  => true,
                'is_controlled'          => true,
                'rack_no'                => true,
                'reorder_quantity'       => true,
                'lead_time_days'         => true,
                'is_batch_tracked'       => true,
                'is_expiry_tracked'      => true,
                'country_of_origin'      => true,
            ],
        ],

        // Expiry with no batch code is the norm — milk, bread, yoghurt.
        BusinessType::SUPERMARKET->value => [
            'spec_label' => 'expiry',
            'sections' => ['lots' => true],
            'defaults' => ['is_expiry_tracked' => true],
            'fields' => [
                'shelf_life_days'        => true,
                'min_shelf_life_percent' => true,
                'storage_zone'           => true,
                'weight'                 => true,
                'rack_no'                => true,
                'reorder_quantity'       => true,
                'lead_time_days'         => true,
                'is_batch_tracked'       => true,
                'is_expiry_tracked'      => true,
                'is_weighted'            => true,
                'default_warehouse'      => true,
            ],
        ],

        BusinessType::GROCERY->value => [
            'spec_label' => 'expiry',
            'sections' => ['lots' => true],
            'defaults' => ['is_expiry_tracked' => true],
            'fields' => [
                'shelf_life_days'   => true,
                'weight'            => true,
                'rack_no'           => true,
                'reorder_quantity'  => true,
                'is_expiry_tracked' => true,
                'is_weighted'       => true,
            ],
        ],

        // Size and colour variants, price tiers, import paperwork.
        BusinessType::CLOTHING->value => [
            'sections' => ['variants' => true],
            'defaults' => ['has_variants' => true, 'is_color_tracked' => true, 'is_size_tracked' => true],
            'fields' => [
                'model'             => true,   // style number
                'rate_a'            => true,
                'rate_b'            => true,
                'country_of_origin' => true,
                'hs_code'           => true,
                'is_color_tracked'  => true,
                'is_size_tracked'   => true,
                'rack_no'           => true,
            ],
        ],

        // IMEI per handset, warranty, storage/colour variants.
        BusinessType::MOBILE->value => [
            'sections' => ['variants' => true, 'pieces' => true],
            'defaults' => ['has_variants' => true, 'is_serial_tracked' => true],
            'fields' => [
                'manufacturer'      => true,
                'model'             => true,
                'warranty_months'   => true,
                'country_of_origin' => true,
                'hs_code'           => true,
                'weight'            => true,
                'is_serial_tracked' => true,
                'is_color_tracked'  => true,
            ],
        ],

        BusinessType::COMPUTER->value => [
            'sections' => ['variants' => true, 'pieces' => true],
            'defaults' => ['has_variants' => true, 'is_serial_tracked' => true],
            'fields' => [
                'manufacturer'      => true,
                'model'             => true,
                'warranty_months'   => true,
                'hs_code'           => true,
                'country_of_origin' => true,
                'is_serial_tracked' => true,
                'is_stockable'      => true,
            ],
        ],

        BusinessType::ELECTRONICS->value => [
            'sections' => ['variants' => true, 'pieces' => true],
            'defaults' => ['has_variants' => true],
            'fields' => [
                'manufacturer'      => true,
                'model'             => true,
                'warranty_months'   => true,
                'hs_code'           => true,
                'country_of_origin' => true,
                'dimensions'        => true,
                'weight'            => true,
                'is_serial_tracked' => true,
            ],
        ],

        // Sold by weight against a daily metal rate; each piece hallmarked.
        BusinessType::JEWELLERY->value => [
            'sections' => ['variants' => true, 'pieces' => true],
            'defaults' => ['has_variants' => true, 'is_weighted' => true],
            'fields' => [
                'weight'            => true,
                'country_of_origin' => true,
                'hs_code'           => true,
                'rack_no'           => true,
                'is_weighted'       => true,
                'is_serial_tracked' => true,
                'pricing_method'    => true,
                'costing_method'    => true,
                'model'             => true,   // design number
            ],
        ],

        // Each piece has its own measured area, so its own cost and price.
        BusinessType::CARPET->value => [
            'sections' => ['variants' => true, 'pieces' => true],
            'defaults' => ['has_variants' => true],
            'fields' => [
                'dimensions'        => true,
                'weight'            => true,
                'country_of_origin' => true,
                'hs_code'           => true,
                'model'             => true,   // design
                'is_color_tracked'  => true,
                'is_serial_tracked' => true,
                'pricing_method'    => true,
                'costing_method'    => true,
            ],
        ],

        BusinessType::PLY_CARPET->value => [
            'sections' => ['variants' => true, 'pieces' => true],
            'defaults' => ['has_variants' => true],
            'fields' => [
                'dimensions'        => true,
                'country_of_origin' => true,
                'hs_code'           => true,
                'model'             => true,
                'pricing_method'    => true,
                'is_serial_tracked' => true,
            ],
        ],

        // Parts carry OEM references; vehicles carry a VIN.
        BusinessType::AUTOMOBILE->value => [
            'sections' => ['variants' => true, 'pieces' => true],
            'defaults' => ['has_variants' => true],
            'fields' => [
                'manufacturer'      => true,
                'model'             => true,
                'country_of_origin' => true,
                'hs_code'           => true,
                'warranty_months'   => true,
                'rack_no'           => true,
                'weight'            => true,
                'dimensions'        => true,
                'is_serial_tracked' => true,
                'reorder_quantity'  => true,
                'lead_time_days'    => true,
            ],
        ],

        // Edition and format are the variants; publisher sits in manufacturer.
        BusinessType::BOOK->value => [
            'sections' => ['variants' => true],
            'defaults' => ['has_variants' => true],
            'fields' => [
                'manufacturer' => true,   // publisher
                'model'        => true,   // edition
                'rack_no'      => true,
                'weight'       => true,
            ],
        ],

        BusinessType::MANUFACTURING->value => [
            'spec_label' => 'lot',
            'sections' => ['variants' => true, 'lots' => true],
            'defaults' => ['is_batch_tracked' => true],
            'fields' => [
                'manufacturer'      => true,
                'model'             => true,
                'weight'            => true,
                'dimensions'        => true,
                'hs_code'           => true,
                'country_of_origin' => true,
                'reorder_quantity'  => true,
                'lead_time_days'    => true,
                'is_batch_tracked'  => true,
                'is_stockable'      => true,
                'is_sellable'       => true,
                'is_purchasable'    => true,
                'costing_method'    => true,
                'default_warehouse' => true,
            ],
        ],

        BusinessType::PHARMA_MANUFACTURING->value => [
            'spec_label' => 'batch',
            'sections' => ['variants' => true, 'lots' => true],
            'defaults' => ['is_batch_tracked' => true, 'is_expiry_tracked' => true],
            'fields' => [
                'generic_name'           => true,
                'manufacturer'           => true,
                'model'                  => true,
                'shelf_life_days'        => true,
                'min_shelf_life_percent' => true,
                'storage_zone'           => true,
                'is_controlled'          => true,
                'lead_time_days'         => true,
                'reorder_quantity'       => true,
                'is_batch_tracked'       => true,
                'is_expiry_tracked'      => true,
                'is_stockable'           => true,
                'is_sellable'            => true,
                'is_purchasable'         => true,
                'costing_method'         => true,
            ],
        ],

        BusinessType::RETAIL->value => [
            'sections' => ['variants' => true],
            'fields' => [
                'rack_no'          => true,
                'rate_a'           => true,
                'reorder_quantity' => true,
                'is_color_tracked' => true,
                'is_size_tracked'  => true,
            ],
        ],

        BusinessType::HOTEL->value => [
            'fields' => [
                'rack_no'      => true,
                'is_stockable' => true,
                'storage_zone' => true,
            ],
        ],

        // Services only — no stock, no openings.
        BusinessType::ACCOUNTING->value => [
            'sections' => ['openings' => false],
            'fields' => [
                'minimum_stock' => false,
                'maximum_stock' => false,
                'packing'       => false,
                'barcode'       => false,
                'sku'           => false,
                'photo'         => false,
                'fast_search'   => false,
                'is_stockable'  => true,
                'is_sellable'   => true,
            ],
        ],
    ],
];
