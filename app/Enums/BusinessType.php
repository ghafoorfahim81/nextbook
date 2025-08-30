<?php

namespace App\Enums;

enum BusinessType: string
{
    case PHARMACY_SHOP = 'pharmacy_shop';
    case PHARMA_DISTRIBUTION = 'pharma_distribution';
    case SUPERMARKET_GROCERY = 'supermarket_grocery';
    case ACCOUNTING = 'accounting';
    case AUTOMOBILE = 'automobile';
    case BILLING_GENERAL = 'billing_general';
    case BOOK_AUTHOR_PUBLISHER = 'book_author_publisher';
    case GARMENT_SIZE_WISE = 'garment_size_wise';
    case HOTEL_RESORTS = 'hotel_resorts';
    case JEWELLERY = 'jewellery';
    case MOBILE_SERIAL_WISE = 'mobile_serial_wise';
    case PHARMA_MANUFACTURING_BATCH = 'pharma_manufacturing_batch';
    case PLY_CARPET_FEET_MTR_WISE = 'ply_carpet_feet_mtr_wise';
    case RESTAURANT_TABLE_WISE = 'restaurant_table_wise';

    public function getLabel(): string
    {
        return match($this) {
            self::PHARMACY_SHOP => __('enums.business_type.pharmacy_shop'),
            self::PHARMA_DISTRIBUTION => __('enums.business_type.pharma_distribution'),
            self::SUPERMARKET_GROCERY => __('enums.business_type.supermarket_grocery'),
            self::ACCOUNTING => __('enums.business_type.accounting'),
            self::AUTOMOBILE => __('enums.business_type.automobile'),
            self::BILLING_GENERAL => __('enums.business_type.billing_general'),
            self::BOOK_AUTHOR_PUBLISHER => __('enums.business_type.book_author_publisher'),
            self::GARMENT_SIZE_WISE => __('enums.business_type.garment_size_wise'),
            self::HOTEL_RESORTS => __('enums.business_type.hotel_resorts'),
            self::JEWELLERY => __('enums.business_type.jewellery'),
            self::MOBILE_SERIAL_WISE => __('enums.business_type.mobile_serial_wise'),
            self::PHARMA_MANUFACTURING_BATCH => __('enums.business_type.pharma_manufacturing_batch'),
            self::PLY_CARPET_FEET_MTR_WISE => __('enums.business_type.ply_carpet_feet_mtr_wise'),
            self::RESTAURANT_TABLE_WISE => __('enums.business_type.restaurant_table_wise'),
        };
    }

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    } 
}
