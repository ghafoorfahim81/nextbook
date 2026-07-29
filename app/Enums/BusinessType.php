<?php

namespace App\Enums;

enum BusinessType: string
{
    case PHARMACY = 'pharmacy'; 
    case SUPERMARKET = 'supermarket';
    case MANUFACTURING = 'manufacturing';
    case RETAIL = 'retail';
    case ACCOUNTING = 'accounting';
    case AUTOMOBILE = 'automobile'; 
    case BOOK = 'book';
    case CLOTHING = 'clothing'; 
    case HOTEL = 'hotel';
    case CARPET = 'carpet';
    case ELECTRONICS = 'electronics';
    case COMPUTER = 'computer';
    case JEWELLERY = 'jewellery';
    case MOBILE = 'mobile';
    case PHARMA_MANUFACTURING = 'pharma_manufacturing';
    case PLY_CARPET = 'ply_carpet';
    case RESTAURANT = 'restaurant';

    public function getLabel(): string
    {
        return match($this) {
            self::PHARMACY => __('enums.business_type.pharmacy'),
            self::SUPERMARKET => __('enums.business_type.supermarket'),
            self::MANUFACTURING => __('enums.business_type.manufacturing'),
            self::RETAIL => __('enums.business_type.retail'),
            self::ACCOUNTING => __('enums.business_type.accounting'),
            self::AUTOMOBILE => __('enums.business_type.automobile'),
            self::BOOK => __('enums.business_type.book'),
            self::CLOTHING => __('enums.business_type.clothing'),
            self::HOTEL => __('enums.business_type.hotel'),
            self::CARPET => __('enums.business_type.carpet'),
            self::ELECTRONICS => __('enums.business_type.electronics'),
            self::COMPUTER => __('enums.business_type.computer'),
            self::JEWELLERY => __('enums.business_type.jewellery'),
            self::MOBILE => __('enums.business_type.mobile'),
            self::PHARMA_MANUFACTURING => __('enums.business_type.pharma_manufacturing'),
            self::PLY_CARPET => __('enums.business_type.ply_carpet'),
            self::RESTAURANT => __('enums.business_type.restaurant'),
        };
    }

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    } 
}
