<?php

return [
    'calendar_type' => [
        'jalali' => 'جلالی',
        'gregorian' => 'میلادی',
    ],
    'working_style' => [
        'normal' => 'عادی',
        'secondary' => 'ثانوی',
    ],
    'business_type' => [
        // (Fallback to English where an accurate Dari/Farsi translation isn't available yet)
        'pharmacy_shop' => 'داروخانه',
        'pharma_distribution' => 'توزیع دارو',
        'supermarket_grocery' => 'سوپرمارکت / خواربار',
        'accounting' => 'حسابداری',
        'automobile' => 'موتر / اتومبیل',
        'billing_general' => 'صدور فاکتور (عمومی)',
        'book_author_publisher' => 'کتاب (نویسنده/ناشر)',
        'garment_size_wise' => 'پوشاک (بر اساس سایز)',
        'hotel_resorts' => 'هوتل و ریزورت',
        'jewellery' => 'زیورات',
        'mobile_serial_wise' => 'موبایل (بر اساس سریال)',
        'pharma_manufacturing_batch' => 'تولید دارو (بچ)',
        'ply_carpet_feet_mtr_wise' => 'پلای/قالین (فوت/متر)',
        'restaurant_table_wise' => 'رستورانت (بر اساس میز)',
    ],
    'locale' => [
        'fa' => 'فارسی',
        'en' => 'انگلیسی',
        'ps' => 'پښتو',
    ],
    'sales_purchase_type' => [
        'cash' => 'نقدی',
        'credit' => 'قرض/اعتباری',
        'on_loan' => 'قرض',
    ],
    'discount_type' => [
        'percentage' => 'فیصدی',
        'currency' => 'مبلغی',
    ],
    'opening_type' => [
        'debit' => 'دبیت',
        'credit' => 'کریدیت',
    ],
    'transaction_type' => [
        'debit' => 'بدهکار',
        'credit' => 'طلبکار',
    ],
    'transaction_status' => [
        'posted' => 'ثبت شده',
        'approved' => 'تأیید شده',
        'rejected' => 'رد شده',
        'cancelled' => 'لغو شده',
        'reversed' => 'معکوس شده',
    ],
    'transfer_status' => [
        'pending' => 'در انتظار',
        'completed' => 'تکمیل شده',
        'cancelled' => 'لغو شده',
    ],
    'user_status' => [
        'active' => 'فعال',
        'inactive' => 'غیرفعال',
        'blocked' => 'مسدود',
    ],
    'ledger_type' => [
        'customer' => 'مشتری',
        'supplier' => 'تأمین‌کننده',
        'employee' => 'کارمند',
    ],
    'item_type' => [
        'inventory_materials' => 'مواد و محصولات انبار',
        'non_inventory_materials' => 'مواد غیر انبار',
        'raw_materials' => 'مواد اولیه',
        'finished_good_items' => 'محصولات تمام شده',
        'inventory_services' => 'خدمات انبار',
    ],
    'stock_direction_type' => [
        'in' => 'ورود',
        'out' => 'خروج',
    ],
    'stock_source_type' => [
        'purchase' => 'خرید',
        'purchase_return' => 'برگشت خرید',
        'sale' => 'فروش',
        'sale_return' => 'برگشت فروش',
        'stock_adjustment' => 'تعدیل انبار',
        'item_transfer' => 'ترجمه مورد',
        'adjustment' => 'تعدیل',
        'opening' => 'موجودی اولیه',
    ],
    'costing_method' => [
        'fifo' => 'اولین ورود اولین خروج',
        'lifo' => 'آخرین ورود اولین خروج',
        'weighted_average' => 'میانگین وزنی',
    ],
    'payment_mode' => [
        'bill_by_bill' => 'بر اساس فاکتور',
        'on_account' => 'بر اساس حساب',
    ],
    'payment_status' => [
        'paid' => 'پرداخت شده',
        'unpaid' => 'باقی',
        'partially_paid' => 'بخشی پرداخت شده',
    ],

    // منابع بشری
    'gender' => [
        'male' => 'مرد',
        'female' => 'زن',
        'other' => 'سایر',
    ],
    'marital_status' => [
        'single' => 'مجرد',
        'married' => 'متأهل',
        'divorced' => 'طلاق‌شده',
        'widowed' => 'بیوه',
    ],
    'employment_type' => [
        'permanent' => 'دایمی',
        'temporary' => 'موقت',
        'contract' => 'قراردادی',
        'consultant' => 'مشاور',
        'intern' => 'کارآموز',
        'daily_wage' => 'مزد روزانه',
    ],
    'employment_status' => [
        'probation' => 'دوره آزمایشی',
        'active' => 'فعال',
        'on_leave' => 'در رخصتی',
        'suspended' => 'معطل',
        'resigned' => 'استعفا داده',
        'terminated' => 'منفک شده',
        'retired' => 'متقاعد',
    ],
    'employee_document_type' => [
        'tazkira' => 'تذکره',
        'passport' => 'پاسپورت',
        'visa' => 'ویزه',
        'work_permit' => 'جواز کار',
        'contract' => 'قرارداد',
        'degree' => 'سند تحصیلی',
        'certificate' => 'تصدیق‌نامه',
        'police_clearance' => 'تصدیق عدم مسئولیت جرمی',
        'medical' => 'صحی',
        'driving_license' => 'جواز رانندگی',
        'other' => 'سایر',
    ],
    'contract_type' => [
        'permanent' => 'دایمی',
        'fixed_term' => 'مدت معین',
        'probation' => 'دوره آزمایشی',
        'consultancy' => 'مشورتی',
        'internship' => 'کارآموزی',
    ],
    'contract_status' => [
        'draft' => 'مسوده',
        'active' => 'فعال',
        'expired' => 'ختم شده',
        'terminated' => 'فسخ شده',
        'renewed' => 'تمدید شده',
    ],
];


