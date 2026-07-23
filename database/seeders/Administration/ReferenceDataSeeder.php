<?php

namespace Database\Seeders\Administration;

use App\Models\Administration\Country;
use App\Models\Administration\CustomerGroup;
use App\Models\Administration\Province;
use Illuminate\Database\Seeder;

class ReferenceDataSeeder extends Seeder
{
    public function run(): void
    {
        $codes = explode(' ', 'AF AL DZ AD AO AG AR AM AU AT AZ BS BH BD BB BY BE BZ BJ BT BO BA BW BR BN BG BF BI CV KH CM CA CF TD CL CN CO KM CG CD CR CI HR CU CY CZ DK DJ DM DO EC EG SV GQ ER EE SZ ET FJ FI FR GA GM GE DE GH GR GD GT GN GW GY HT HN HU IS IN ID IR IQ IE IL IT JM JP JO KZ KE KI KP KR KW KG LA LV LB LS LR LY LI LT LU MG MW MY MV ML MT MH MR MU MX FM MD MC MN ME MA MZ MM NA NR NP NL NZ NI NE NG MK NO OM PK PW PA PG PY PE PH PL PT QA RO RU RW KN LC VC WS SM ST SA SN RS SC SL SG SK SI SB SO ZA SS ES LK SD SR SE CH SY TJ TZ TH TL TG TO TT TN TR TM TV UG UA AE GB US UY UZ VU VE VN YE ZM ZW');

        foreach ($codes as $code) {
            Country::query()->updateOrCreate(
                ['code' => $code],
                [
                    'name_en' => \Locale::getDisplayRegion('und_'.$code, 'en') ?: $code,
                    'name_fa' => \Locale::getDisplayRegion('und_'.$code, 'fa') ?: $code,
                ],
            );
        }

        foreach ([
            ['Wholesale', 'عمده فروشی'],
            ['Retail', 'خرده فروشی'],
            ['VIP', 'ویژه'],
            ['Government', 'دولتی'],
            ['Online', 'آنلاین'],
        ] as [$nameEn, $nameFa]) {
            CustomerGroup::query()->firstOrCreate(
                ['branch_id' => null, 'name_en' => $nameEn],
                ['name_fa' => $nameFa],
            );
        }

        $afghanistan = Country::query()->where('code', 'AF')->firstOrFail();
        foreach ([
            ['Badakhshan', 'بدخشان'], ['Badghis', 'بادغیس'], ['Baghlan', 'بغلان'], ['Balkh', 'بلخ'],
            ['Bamyan', 'بامیان'], ['Daykundi', 'دایکندی'], ['Farah', 'فراه'], ['Faryab', 'فاریاب'],
            ['Ghazni', 'غزنی'], ['Ghor', 'غور'], ['Helmand', 'هلمند'], ['Herat', 'هرات'],
            ['Jowzjan', 'جوزجان'], ['Kabul', 'کابل'], ['Kandahar', 'کندهار'], ['Kapisa', 'کاپیسا'],
            ['Khost', 'خوست'], ['Kunar', 'کنر'], ['Kunduz', 'کندز'], ['Laghman', 'لغمان'],
            ['Logar', 'لوگر'], ['Nangarhar', 'ننگرهار'], ['Nimruz', 'نیمروز'], ['Nuristan', 'نورستان'],
            ['Paktia', 'پکتیا'], ['Paktika', 'پکتیکا'], ['Panjshir', 'پنجشیر'], ['Parwan', 'پروان'],
            ['Samangan', 'سمنگان'], ['Sar-e Pol', 'سرپل'], ['Takhar', 'تخار'], ['Uruzgan', 'ارزگان'],
            ['Wardak', 'میدان وردک'], ['Zabul', 'زابل'],
        ] as [$nameEn, $nameFa]) {
            Province::query()->updateOrCreate(
                ['country_id' => $afghanistan->id, 'name_en' => $nameEn],
                ['name_fa' => $nameFa],
            );
        }
    }
}
