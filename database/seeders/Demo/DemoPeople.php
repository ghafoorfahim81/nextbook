<?php

namespace Database\Seeders\Demo;

/**
 * Afghan names, businesses, addresses and phone numbers for the demo data.
 * Every business name is invented; none belongs to a real company.
 */
final class DemoPeople
{
    private const FIRST = ['احمد', 'محمد', 'عبدالله', 'نجیب‌الله', 'حمیدالله', 'فرید', 'جاوید', 'شفیق', 'ضیاالرحمن', 'عبدالرحیم',
        'نصیر', 'بشیر', 'قدیر', 'رحمت‌الله', 'عزیزالله', 'سمیع‌الله', 'ذبیح‌الله', 'خالد', 'ولی', 'مصطفی', 'یونس', 'عبدالقادر',
        'نورالله', 'حبیب‌الله', 'اسدالله', 'شکیب', 'مسعود', 'فهیم', 'رامین', 'سهراب', 'امید', 'نوید', 'همایون', 'جمشید',
        'عبدالغفور', 'گل‌آقا', 'شیرآقا', 'میرویس', 'زلمی', 'ننگیالی', 'وحیدالله', 'اجمل', 'فواد', 'ظاهر', 'کریم', 'رسول',
        'عارف', 'سید آغا', 'حاجی نبی', 'حاجی قاسم', 'مریم', 'فاطمه', 'زهرا', 'لیلا', 'نرگس', 'شکریه', 'پروین', 'فریبا', 'ملالی', 'هما'];

    private const LAST = ['احمدزی', 'پوپلزی', 'هوتک', 'حکیمی', 'رحیمی', 'نوری', 'صدیقی', 'کریمی', 'محمدی', 'حیدری', 'سلطانی',
        'عزیزی', 'نظری', 'قادری', 'رسولی', 'امینی', 'یوسفزی', 'کاکړ', 'ستانکزی', 'وردک', 'شینواری', 'مومند', 'تاجیک', 'اکبری',
        'جلالی', 'فاروقی', 'هاشمی', 'سادات', 'نیازی', 'غوری', 'بلخی', 'هروی', 'کابلی', 'بدخشی', 'پنجشیری', 'اندرابی', 'لودین', 'خروتی'];

    private const SHOP_PREFIX = ['سوپرمارکیت', 'فروشگاه', 'دوکان', 'مغازه', 'عمده‌فروشی', 'مارکیت', 'تجارتخانه', 'نمایندگی'];

    private const SHOP_NAME = ['نور', 'امید', 'آریانا', 'پامیر', 'هندوکش', 'سپین‌غر', 'بهار', 'ستاره', 'کوه نور', 'سیمرغ', 'شمال',
        'آمو', 'هریرود', 'پیمان', 'اتفاق', 'برادران', 'صداقت', 'امانت', 'فامیل', 'شهر نو', 'مهتاب', 'آفتاب', 'زرغون', 'گلستان',
        'میوند', 'بامیان', 'پغمان', 'سالنگ', 'خیبر', 'اسپین بولدک'];

    private const COMPANY_KIND = ['شرکت تجارتی', 'شرکت واردات و صادرات', 'شرکت توزیعی', 'شرکت تولیدی', 'تجارتخانه', 'شرکت لوژستیکی و تجارتی'];

    private const COMPANY_SUFFIX = ['لمیتد', 'گروپ', 'و شرکا', 'برادران', ''];

    public const CITIES = [
        'کابل' => ['کارته سه', 'کارته چهار', 'شهر نو', 'مکرویان', 'خیرخانه', 'تایمنی', 'دشت برچی', 'کوته سنگی', 'وزیر اکبرخان',
            'قلعه فتح‌الله', 'چهارراهی قنبر', 'پل سوخته', 'شاه شهید', 'ده افغانان', 'مندوی', 'سرای شهزاده', 'کارته نو', 'بگرامی'],
        'هرات' => ['جاده ولایت', 'شهر نو هرات', 'چوک گلها', 'بازار ملک', 'دروازه عراق'],
        'مزار شریف' => ['کارته بلخ', 'جاده بلخ', 'روضه', 'دهدادی', 'شهرک آریانا'],
        'قندهار' => ['شهر نو قندهار', 'عینو مینه', 'بازار شکارپور'],
        'جلال‌آباد' => ['چوک تلاشی', 'ناحیه سوم', 'بازار جلال‌آباد'],
        'کندز' => ['سرک فیض‌آباد', 'بازار کندز'],
        'غزنی' => ['بازار غزنی', 'شهر نو غزنی'],
        'پلخمری' => ['بازار پلخمری'],
    ];

    /** Customers and suppliers are mostly in Kabul, where the shop is. */
    private const CITY_WEIGHTS = ['کابل' => 70, 'هرات' => 5, 'مزار شریف' => 6, 'قندهار' => 4, 'جلال‌آباد' => 6, 'کندز' => 3, 'غزنی' => 3, 'پلخمری' => 3];

    public function __construct(private DemoRandom $random)
    {
    }

    public function person(): string
    {
        return $this->random->pick(self::FIRST) . ' ' . $this->random->pick(self::LAST);
    }

    public function shop(): string
    {
        return $this->random->pick(self::SHOP_PREFIX) . ' ' . $this->random->pick(self::SHOP_NAME);
    }

    public function company(): string
    {
        $owner = $this->random->chance(0.5)
            ? $this->random->pick(self::LAST)
            : $this->random->pick(self::SHOP_NAME);

        return trim($this->random->pick(self::COMPANY_KIND) . ' ' . $owner . ' ' . $this->random->pick(self::COMPANY_SUFFIX));
    }

    /** @return array{city: string, address: string} */
    public function address(?string $city = null): array
    {
        $city ??= (string) $this->random->weighted(self::CITY_WEIGHTS);
        $area = $this->random->pick(self::CITIES[$city]);

        $detail = $this->random->pick([
            'سرک ' . $this->random->int(1, 15),
            'کوچه ' . $this->random->int(1, 30),
            'مارکیت ' . $this->random->pick(self::SHOP_NAME) . '، منزل ' . $this->random->int(1, 4),
            'نزدیک ' . $this->random->pick(['مسجد جامع', 'چهارراهی', 'شفاخانه', 'مکتب', 'پارک']),
        ]);

        return ['city' => $city, 'address' => "{$city}، {$area}، {$detail}"];
    }

    public function phone(): string
    {
        $prefix = $this->random->pick(['70', '72', '73', '74', '76', '77', '78', '79']);

        // The ledger form accepts digits only, with an optional leading +.
        return '+93' . $prefix . sprintf('%07d', $this->random->int(0, 9999999));
    }
}
