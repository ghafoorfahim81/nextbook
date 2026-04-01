<?php

namespace App\Models\Administration;

use App\Traits\HasBranch;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Traits\HasUserAuditable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use App\Traits\HasSearch;
use App\Traits\HasSorting;
use App\Traits\HasCache;
use App\Traits\HasUserTracking;
use Symfony\Component\Uid\Ulid;
use App\Traits\BranchSpecific;
use Illuminate\Database\Eloquent\Relations\HasMany;
class Currency extends Model
{
    use HasFactory, HasUserAuditable, HasUserTracking, HasUlids, HasCache, HasSearch, HasSorting, BranchSpecific, HasBranch, SoftDeletes;


    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $keyType = 'string';
    public $incrementing = false; // Disable auto-incrementing

    protected $fillable = [
        'name',
        'code',
        'symbol',
        'format',
        'exchange_rate',
        'is_active',
        'is_base_currency',
        'flag',
        'branch_id',
        'created_by',
        'updated_by',
    ];

    protected static function boot()
    {
        parent::boot();
        static::creating(function ($model) {
            $model->id = (string) new Ulid(); // Generate ULID
        });
    }

    /**
     * The attributes that should be cast to native types.
     *
     * @var array
     */
    protected $casts = [
        'id' => 'string',
        'is_active' => 'boolean',
        'is_base_currency' => 'boolean',
        'branch_id' => 'string',
        'tenant_id' => 'string',
        'created_by' => 'string',
        'updated_by' => 'string',
    ];

    protected static function searchableColumns(): array
    {
        return [
            'name',
            'code',
            'symbol',
            'format',
            'flag',
            'branch.name',
        ];
    }

    public static function defaultCurrencies(): array
    {
        return [
            'AFN' => [
                'name' => 'Afghanistan, Afghani',
                'code' => 'AFN',
                'symbol' => '؋',
                'format' => '؋1,0.00',
                'exchange_rate' => 1,
                'flag'     => 'af.png',
                'is_active' => true,
                'is_base_currency' => true,
            ],
            'USD' => [
                'name' => 'US Dollar',
                'code' => 'USD',
                'symbol' => '$',
                'format' => '$1,0.00',
                'exchange_rate' => 0.00,
                'flag' => 'us.png',
                'is_active' => true,
                'is_base_currency' => false,
            ],
            'IRR' => [
                'name' => 'Iranian Rial',
                'code' => 'IRR',
                'symbol' => '﷼',
                'format' => '﷼ 1,0/00',
                'exchange_rate' => 0.00,
                'flag'     => 'ir.png',
                'is_active' => true,
                'is_base_currency' => false,
            ],
            'INR' => [
                'name' => 'Indian Rupee',
                'code' => 'INR',
                'symbol' => '₹',
                'format' => '1,0.00₹',
                'exchange_rate' => 0.00,
                'flag'     => 'in.png',
                'is_active' => true,
                'is_base_currency' => false,
            ],
        ];
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function rateUpdates(): HasMany
    {
        return $this->hasMany(CurrencyRateUpdate::class);
    }

    public static function currencyList(): array
    {
        return  [
            'AFN' => [
                'name' => 'Afghanistan, Afghani',
                'symbol' => '؋',
                'format' => '؋1,0.00',
                'exchange_rate' => 0.00,
                'flag'     => 'af.png',
            ],
            'AED' => [
                'name' => 'UAE Dirham',
                'symbol' => 'دإ‏',
                'format' => 'دإ‏ 1,0.00',
                'exchange_rate' => 0.00,
                'flag'     => 'ae.png',
            ],
            'ALL' => [
                'name' => 'Albania, Lek',
                'symbol' => 'Lek',
                'format' => '1,0.00Lek',
                'exchange_rate' => 0.00,
                'flag'     => 'al.png',
            ],
            'AMD' => [
                'name' => 'Armenian Dram',
                'symbol' => '&#1423;',
                'format' => '1,0.00 &#1423;',
                'exchange_rate' => 0.00,
                'flag'     => 'am.png',
            ],
            'ANG' => [
                'name' => 'Netherlands Antillian Guilder',
                'symbol' => 'ƒ',
                'format' => 'ƒ1,0.00',
                'exchange_rate' => 0.00,
                'flag'     => 'an.png',
            ],
            'AOA' => [
                'name' => 'Angola, Kwanza',
                'symbol' => 'Kz',
                'format' => 'Kz1,0.00',
                'exchange_rate' => 0.00,
                'flag'     => 'ao.png',
            ],
            'ARS' => [
                'name' => 'Argentine Peso',
                'symbol' => '$',
                'format' => '$ 1,0.00',
                'exchange_rate' => 0.00,
                'flag'     => 'ar.png',
            ],
            'AUD' => [
                'name' => 'Australian Dollar',
                'symbol' => '$',
                'format' => '$1,0.00',
                'exchange_rate' => 0.00,
                'flag'     => 'au.png',
            ],
            'AWG' => [
                'name' => 'Aruban Guilder',
                'symbol' => 'ƒ',
                'format' => 'ƒ1,0.00',
                'exchange_rate' => 0.00,
                'flag'     => 'aw.png',
            ],
            'AZN' => [
                'name' => 'Azerbaijanian Manat',
                'symbol' => '₼',
                'format' => '1 0,00 ₼',
                'exchange_rate' => 0.00,
                'flag'     => 'az.png',
            ],
            'BAM' => [
                'name' => 'Bosnia and Herzegovina, Convertible Marks',
                'symbol' => 'КМ',
                'format' => '1,0.00 КМ',
                'exchange_rate' => 0.00,
                'flag'     => 'ba.png',
            ],
            'BBD' => [
                'name' => 'Barbados Dollar',
                'symbol' => '$',
                'format' => '$1,0.00',
                'exchange_rate' => 0.00,
                'flag'     => 'bb.png',
            ],
            'BDT' => [
                'name' => 'Bangladesh, Taka',
                'symbol' => '৳',
                'format' => '৳ 1,0.',
                'exchange_rate' => 0.00,
                'flag'     => 'bd.png',
            ],
            'BGN' => [
                'name' => 'Bulgarian Lev',
                'symbol' => 'лв.',
                'format' => '1 0,00 лв.',
                'exchange_rate' => 0.00,
                'flag'     => 'bg.png',
            ],
            'BHD' => [
                'name' => 'Bahraini Dinar',
                'symbol' => '.د.',
                'format' => '.د. 1,0.000',
                'exchange_rate' => 0.00,
                'flag'     => 'bh.png',
            ],
            'BIF' => [
                'name' => 'Burundi Franc',
                'symbol' => 'FBu',
                'format' => '1,0.FBu',
                'exchange_rate' => 0.00,
                'flag'     => 'bi.png',
            ],
            'BMD' => [
                'name' => 'Bermudian Dollar',
                'symbol' => '$',
                'format' => '$1,0.00',
                'exchange_rate' => 0.00,
                'flag'     => 'bm.png',
            ],
            'BND' => [
                'name' => 'Brunei Dollar',
                'symbol' => '$',
                'format' => '$1,0.',
                'exchange_rate' => 0.00,
                'flag'     => 'bn.png',
            ],
            'BOB' => [
                'name' => 'Bolivia, Boliviano',
                'symbol' => 'Bs',
                'format' => 'Bs 1,0.00',
                'exchange_rate' => 0.00,
                'flag'     => 'bo.png',
            ],
            'BRL' => [
                'name' => 'Brazilian Real',
                'symbol' => 'R$',
                'format' => 'R$ 1,0.00',
                'exchange_rate' => 0.00,
                'flag'     => 'br.png',
            ],
            'BSD' => [
                'name' => 'Bahamian Dollar',
                'symbol' => '$',
                'format' => '$1,0.00',
                'exchange_rate' => 0.00,
                'flag'     => 'bs.png',
            ],
            'BTN' => [
                'name' => 'Bhutan, Ngultrum',
                'symbol' => 'Nu.',
                'format' => 'Nu. 1,0.0',
                'exchange_rate' => 0.00,
                'flag'     => 'bt.png',
            ],
            'BWP' => [
                'name' => 'Botswana, Pula',
                'symbol' => 'P',
                'format' => 'P1,0.00',
                'exchange_rate' => 0.00,
                'flag'     => 'bw.png',
            ],
            'BYR' => [
                'name' => 'Belarussian Ruble',
                'symbol' => 'р.',
                'format' => '1 0,00 р.',
                'exchange_rate' => 0.00,
                'flag'     => 'by.png',
            ],
            'BZD' => [
                'name' => 'Belize Dollar',
                'symbol' => 'BZ$',
                'format' => 'BZ$1,0.00',
                'exchange_rate' => 0.00,
                'flag'     => 'bz.png',
            ],
            'CAD' => [
                'name' => 'Canadian Dollar',
                'symbol' => '$',
                'format' => '$1,0.00',
                'exchange_rate' => 0.00,
                'flag'     => 'ca.png',
            ],
            'CDF' => [
                'name' => 'Franc Congolais',
                'symbol' => 'FC',
                'format' => '1,0.00FC',
                'exchange_rate' => 0.00,
                'flag'     => 'cd.png',
            ],
            'CHF' => [
                'name' => 'Swiss Franc',
                'symbol' => 'CHF',
                'format' => '1\'0.00 CHF',
                'exchange_rate' => 0.00,
                'flag'     => 'ca.png',
            ],
            'CLP' => [
                'name' => 'Chilean Peso',
                'symbol' => '$',
                'format' => '$ 1,0.00',
                'exchange_rate' => 0.00,
                'flag'     => 'cl.png',
            ],
            'CNY' => [
                'name' => 'China Yuan Renminbi',
                'symbol' => '¥',
                'format' => '¥1,0.00',
                'exchange_rate' => 0.00,
                'flag'     => 'cn.png',
            ],
            'COP' => [
                'name' => 'Colombian Peso',
                'symbol' => '$',
                'format' => '$ 1,0.00',
                'exchange_rate' => 0.00,
                'flag'     => 'co.png',
            ],
            'CRC' => [
                'name' => 'Costa Rican Colon',
                'symbol' => '₡',
                'format' => '₡1,0.00',
                'exchange_rate' => 0.00,
                'flag'     => 'cr.png',
            ],
            'CUC' => [
                'name' => 'Cuban Convertible Peso',
                'symbol' => 'CUC',
                'format' => 'CUC1,0.00',
                'exchange_rate' => 0.00,
                'flag'     => 'cu.png',
            ],
            'CUP' => [
                'name' => 'Cuban Peso',
                'symbol' => '$MN',
                'format' => '$MN1,0.00',
                'exchange_rate' => 0.00,
                'flag'     => 'cu.png',
            ],
            'CVE' => [
                'name' => 'Cape Verde Escudo',
                'symbol' => '$',
                'format' => '$1,0.00',
                'exchange_rate' => 0.00,
                'flag'     => 'cv.png',
            ],
            'CZK' => [
                'name' => 'Czech Koruna',
                'symbol' => 'Kč',
                'format' => '1 0,00 Kč',
                'exchange_rate' => 0.00,
                'flag'     => 'cz.png',
            ],
            'DJF' => [
                'name' => 'Djibouti Franc',
                'symbol' => 'Fdj',
                'format' => '1,0.Fdj',
                'exchange_rate' => 0.00,
                'flag'     => 'dj.png',
            ],
            'DKK' => [
                'name' => 'Danish Krone',
                'symbol' => 'kr.',
                'format' => '1 0,00 kr.',
                'exchange_rate' => 0.00,
                'flag'     => 'dk.png',
            ],
            'DOP' => [
                'name' => 'Dominican Peso',
                'symbol' => 'RD$',
                'format' => 'RD$1,0.00',
                'exchange_rate' => 0.00,
                'flag'     => 'do.png',
            ],
            'DZD' => [
                'name' => 'Algerian Dinar',
                'symbol' => 'د.ج‏',
                'format' => 'د.ج‏ 1,0.00',
                'exchange_rate' => 0.00,
                'flag'     => 'dz.png',
            ],
            'EGP' => [
                'name' => 'Egyptian Pound',
                'symbol' => 'ج.م',
                'format' => 'ج.م 1,0.00',
                'exchange_rate' => 0.00,
                'flag'     => 'eg.png',
            ],
            'ERN' => [
                'name' => 'Eritrea, Nakfa',
                'symbol' => 'Nfk',
                'format' => '1,0.00Nfk',
                'exchange_rate' => 0.00,
                'flag'     => 'er.png',
            ],
            'ETB' => [
                'name' => 'Ethiopian Birr',
                'symbol' => 'ETB',
                'format' => 'ETB1,0.00',
                'exchange_rate' => 0.00,
                'flag'     => 'et.png',
            ],
            'EUR' => [
                'name' => 'Euro',
                'symbol' => '€',
                'format' => '1.0,00 €',
                'exchange_rate' => 0.00,
                'flag'     => 'eu.png',
            ],
            'FJD' => [
                'name' => 'Fiji Dollar',
                'symbol' => '$',
                'format' => '$1,0.00',
                'exchange_rate' => 0.00,
                'flag'     => 'ej.png',
            ],
            'FKP' => [
                'name' => 'Falkland Islands Pound',
                'symbol' => '£',
                'format' => '£1,0.00',
                'exchange_rate' => 0.00,
                'flag'     => 'fk.png',
            ],
            'GBP' => [
                'name' => 'Pound Sterling',
                'symbol' => '£',
                'format' => '£1,0.00',
                'exchange_rate' => 0.00,
                'flag'     => 'gb.png',
            ],
            'GEL' => [
                'name' => 'Georgia, Lari',
                'symbol' => 'Lari',
                'format' => '1 0,00 Lari',
                'exchange_rate' => 0.00,
                'flag'     => 'ge.png',
            ],
            'GHS' => [
                'name' => 'Ghana Cedi',
                'symbol' => '₵',
                'format' => '₵1,0.00',
                'exchange_rate' => 0.00,
                'flag'     => 'gh.png',
            ],
            'GIP' => [
                'name' => 'Gibraltar Pound',
                'symbol' => '£',
                'format' => '£1,0.00',
                'exchange_rate' => 0.00,
                'flag'     => 'gi.png',
            ],
            'GMD' => [
                'name' => 'Gambia, Dalasi',
                'symbol' => 'D',
                'format' => '1,0.00D',
                'exchange_rate' => 0.00,
                'flag'     => 'gm.png',
            ],
            'GTQ' => [
                'name' => 'Guatemala, Quetzal',
                'symbol' => 'Q',
                'format' => 'Q1,0.00',
                'exchange_rate' => 0.00,
                'flag'     => 'gt.png',
            ],
            'GYD' => [
                'name' => 'Guyana Dollar',
                'symbol' => '$',
                'format' => '$1,0.00',
                'exchange_rate' => 0.00,
                'flag'     => 'gy.png',
            ],
            'HKD' => [
                'name' => 'Hong Kong Dollar',
                'symbol' => 'HK$',
                'format' => 'HK$1,0.00',
                'exchange_rate' => 0.00,
                'flag'     => 'hk.png',
            ],
            'HNL' => [
                'name' => 'Honduras, Lempira',
                'symbol' => 'L.',
                'format' => 'L. 1,0.00',
                'exchange_rate' => 0.00,
                'flag'     => 'hn.png',
            ],
            'HRK' => [
                'name' => 'Croatian Kuna',
                'symbol' => 'kn',
                'format' => '1,0.00 kn',
                'exchange_rate' => 0.00,
                'flag'     => 'hr.png',
            ],
            'HTG' => [
                'name' => 'Haiti, Gourde',
                'symbol' => 'G',
                'format' => 'G1,0.00',
                'exchange_rate' => 0.00,
                'flag'     => 'ht.png',
            ],
            'HUF' => [
                'name' => 'Hungary, Forint',
                'symbol' => 'Ft',
                'format' => '1 0,00 Ft',
                'exchange_rate' => 0.00,
                'flag'     => 'hu.png',
            ],
            'IDR' => [
                'name' => 'Indonesia, Rupiah',
                'symbol' => 'Rp',
                'format' => 'Rp1,0.',
                'exchange_rate' => 0.00,
                'flag'     => 'id.png',
            ],
            'ILS' => [
                'name' => 'New Israeli Shekel',
                'symbol' => '₪',
                'format' => '₪ 1,0.00',
                'exchange_rate' => 0.00,
                'flag'     => 'il.png',
            ],
            'INR' => [
                'name' => 'Indian Rupee',
                'symbol' => '₹',
                'format' => '1,0.00₹',
                'exchange_rate' => 0.00,
                'flag'     => 'in.png',
            ],
            'IQD' => [
                'name' => 'Iraqi Dinar',
                'symbol' => 'د.ع.‏',
                'format' => 'د.ع.‏ 1,0.00',
                'exchange_rate' => 0.00,
                'flag'     => 'iq.png',
            ],
            'IRR' => [
                'name' => 'Iranian Rial',
                'symbol' => '﷼',
                'format' => '﷼ 1,0/00',
                'exchange_rate' => 0.00,
                'flag'     => 'ir.png',
            ],
            'ISK' => [
                'name' => 'Iceland Krona',
                'symbol' => 'kr.',
                'format' => '1,0. kr.',
                'exchange_rate' => 0.00,
                'flag'     => 'is.png',
            ],
            'JMD' => [
                'name' => 'Jamaican Dollar',
                'symbol' => 'J$',
                'format' => 'J$1,0.00',
                'exchange_rate' => 0.00,
                'flag'     => 'jm.png',
            ],
            'JOD' => [
                'name' => 'Jordanian Dinar',
                'symbol' => 'د.ا.‏',
                'format' => 'د.ا.‏ 1,0.000',
                'exchange_rate' => 0.00,
                'flag'     => 'jo.png',
            ],
            'JPY' => [
                'name' => 'Japan, Yen',
                'symbol' => '¥',
                'format' => '¥1,0.',
                'exchange_rate' => 0.00,
                'flag'     => 'jp.png',
            ],
            'KES' => [
                'name' => 'Kenyan Shilling',
                'symbol' => 'S',
                'format' => 'S1,0.00',
                'exchange_rate' => 0.00,
                'flag'     => 'ke.png',
            ],
            'KGS' => [
                'name' => 'Kyrgyzstan, Som',
                'symbol' => 'сом',
                'format' => '1 0-00 сом',
                'exchange_rate' => 0.00,
                'flag'     => 'kg.png',
            ],
            'KHR' => [
                'name' => 'Cambodia, Riel',
                'symbol' => '៛',
                'format' => '1,0.៛',
                'exchange_rate' => 0.00,
                'flag'     => 'kh.png',
            ],
            'KMF' => [
                'name' => 'Comoro Franc',
                'symbol' => 'CF',
                'format' => '1,0.00CF',
                'exchange_rate' => 0.00,
                'flag'     => 'km.png',
            ],
            'KPW' => [
                'name' => 'North Korean Won',
                'symbol' => '₩',
                'format' => '₩1,0.',
                'exchange_rate' => 0.00,
                'flag'     => 'kp.png',
            ],
            'KRW' => [
                'name' => 'South Korea, Won',
                'symbol' => '₩',
                'format' => '₩1,0.',
                'exchange_rate' => 0.00,
                'flag'     => 'kr.png',
            ],
            'KWD' => [
                'name' => 'Kuwaiti Dinar',
                'symbol' => 'دينار‌‌‏',
                'format' => 'دينار‌‌‏ 1,0.000',
                'exchange_rate' => 0.00,
                'flag'     => 'kw.png',
            ],
            'KYD' => [
                'name' => 'Cayman Islands Dollar',
                'symbol' => '$',
                'format' => '$1,0.00',
                'exchange_rate' => 0.00,
                'flag'     => 'ky.png',
            ],
            'KZT' => [
                'name' => 'Kazakhstan, Tenge',
                'symbol' => '₸',
                'format' => '₸1 0-00',
                'exchange_rate' => 0.00,
                'flag'     => 'kz.png',
            ],
            'LAK' => [
                'name' => 'Laos, Kip',
                'symbol' => '₭',
                'format' => '1,0.₭',
                'exchange_rate' => 0.00,
                'flag'     => 'la.png',
            ],
            'LBP' => [
                'name' => 'Lebanese Pound',
                'symbol' => 'ل.ل.‏',
                'format' => 'ل.ل.‏ 1,0.00',
                'exchange_rate' => 0.00,
                'flag'     => 'lb.png',
            ],
            'LKR' => [
                'name' => 'Sri Lanka Rupee',
                'symbol' => '₨',
                'format' => '₨ 1,0.',
                'exchange_rate' => 0.00,
                'flag'     => 'lk.png',
            ],
            'LRD' => [
                'name' => 'Liberian Dollar',
                'symbol' => '$',
                'format' => '$1,0.00',
                'exchange_rate' => 0.00,
                'flag'     => 'lr.png',
            ],
            'LSL' => [
                'name' => 'Lesotho, Loti',
                'symbol' => 'M',
                'format' => '1,0.00M',
                'exchange_rate' => 0.00,
                'flag' => 'ls.png',
            ],
            'LYD' => [
                'name' => 'Libyan Dinar',
                'symbol' => 'د.ل.‏',
                'format' => 'د.ل.‏1,0.000',
                'exchange_rate' => 0.00,
                'flag' => 'ly.png',
            ],
            'MAD' => [
                'name' => 'Moroccan Dirham',
                'symbol' => 'د.م.‏',
                'format' => 'د.م.‏ 1,0.00',
                'exchange_rate' => 0.00,
                'flag' => 'ma.png',
            ],
            'MDL' => [
                'name' => 'Moldovan Leu',
                'symbol' => 'lei',
                'format' => '1,0.00 lei',
                'exchange_rate' => 0.00,
                'flag' => 'md.png',
            ],
            'MGA' => [
                'name' => 'Malagasy Ariary',
                'symbol' => 'Ar',
                'format' => 'Ar1,0.',
                'exchange_rate' => 0.00,
                'flag' => 'mg.png',
            ],
            'MKD' => [
                'name' => 'Macedonia, Denar',
                'symbol' => 'ден.',
                'format' => '1,0.00 ден.',
                'exchange_rate' => 0.00,
                'flag' => 'mk.png',
            ],
            'MMK' => [
                'name' => 'Myanmar, Kyat',
                'symbol' => 'K',
                'format' => 'K1,0.00',
                'exchange_rate' => 0.00,
                'flag' => 'mm.png',
            ],
            'MNT' => [
                'name' => 'Mongolia, Tugrik',
                'symbol' => '₮',
                'format' => '₮1 0,00',
                'exchange_rate' => 0.00,
                'flag' => 'mn.png',
            ],
            'MOP' => [
                'name' => 'Macao, Pataca',
                'symbol' => 'MOP$',
                'format' => 'MOP$1,0.00',
                'exchange_rate' => 0.00,
                'flag' => 'mo.png',
            ],
            'MRO' => [
                'name' => 'Mauritania, Ouguiya',
                'symbol' => 'UM',
                'format' => '1,0.00UM',
                'exchange_rate' => 0.00,
                'flag' => 'mr.png',
            ],
            'MTL' => [
                'name' => 'Maltese Lira',
                'symbol' => '₤',
                'format' => '₤1,0.00',
                'exchange_rate' => 0.00,
                'flag' => 'mt.png',
            ],
            'MUR' => [
                'name' => 'Mauritius Rupee',
                'symbol' => '₨',
                'format' => '₨1,0.00',
                'exchange_rate' => 0.00,
                'flag' => 'mu.png',
            ],
            'MVR' => [
                'name' => 'Maldives, Rufiyaa',
                'symbol' => 'MVR',
                'format' => '1,0.0 MVR',
                'exchange_rate' => 0.00,
                'flag' => 'mv.png',
            ],
            'MWK' => [
                'name' => 'Malawi, Kwacha',
                'symbol' => 'MK',
                'format' => 'MK1,0.00',
                'exchange_rate' => 0.00,
                'flag' => 'mw.png',
            ],
            'MXN' => [
                'name' => 'Mexican Peso',
                'symbol' => '$',
                'format' => '$1,0.00',
                'exchange_rate' => 0.00,
                'flag' => 'mx.png',
            ],
            'MYR' => [
                'name' => 'Malaysian Ringgit',
                'symbol' => 'RM',
                'format' => 'RM1,0.00',
                'exchange_rate' => 0.00,
                'flag' => 'my.png',
            ],
            'MZN' => [
                'name' => 'Mozambique Metical',
                'symbol' => 'MT',
                'format' => 'MT1,0.',
                'exchange_rate' => 0.00,
                'flag' => 'mz.png',
            ],
            'NAD' => [
                'name' => 'Namibian Dollar',
                'symbol' => '$',
                'format' => '$1,0.00',
                'exchange_rate' => 0.00,
                'flag' => 'na.png',
            ],
            'NGN' => [
                'name' => 'Nigeria, Naira',
                'symbol' => '₦',
                'format' => '₦1,0.00',
                'exchange_rate' => 0.00,
                'flag' => 'mg.png',
            ],
            'NIO' => [
                'name' => 'Nicaragua, Cordoba Oro',
                'symbol' => 'C$',
                'format' => 'C$ 1,0.00',
                'exchange_rate' => 0.00,
                'flag' => 'ni.png',
            ],
            'NOK' => [
                'name' => 'Norwegian Krone',
                'symbol' => 'kr',
                'format' => '1.0,00 kr',
                'exchange_rate' => 0.00,
                'flag' => 'no.png',
            ],
            'NPR' => [
                'name' => 'Nepalese Rupee',
                'symbol' => '₨',
                'format' => '₨1,0.00',
                'exchange_rate' => 0.00,
                'flag' => 'np.png',
            ],
            'NZD' => [
                'name' => 'New Zealand Dollar',
                'symbol' => '$',
                'format' => '$1,0.00',
                'exchange_rate' => 0.00,
                'flag' => 'nz.png',
            ],
            'OMR' => [
                'name' => 'Rial Omani',
                'symbol' => '﷼',
                'format' => '﷼ 1,0.000',
                'exchange_rate' => 0.00,
                'flag' => 'om.png',
            ],
            'PAB' => [
                'name' => 'Panama, Balboa',
                'symbol' => 'B/.',
                'format' => 'B/. 1,0.00',
                'exchange_rate' => 0.00,
                'flag' => 'pa.png',
            ],
            'PEN' => [
                'name' => 'Peru, Nuevo Sol',
                'symbol' => 'S/.',
                'format' => 'S/. 1,0.00',
                'exchange_rate' => 0.00,
                'flag' => 'pe.png',
            ],
            'PGK' => [
                'name' => 'Papua New Guinea, Kina',
                'symbol' => 'K',
                'format' => 'K1,0.00',
                'exchange_rate' => 0.00,
                'flag' => 'pg.png',
            ],
            'PHP' => [
                'name' => 'Philippine Peso',
                'symbol' => '₱',
                'format' => '₱1,0.00',
                'exchange_rate' => 0.00,
                'flag' => 'ph.png',
            ],
            'PKR' => [
                'name' => 'Pakistan Rupee',
                'symbol' => '₨',
                'format' => '₨1,0.00',
                'exchange_rate' => 0.00,
                'flag' => 'pk.png',
            ],
            'PLN' => [
                'name' => 'Poland, Zloty',
                'symbol' => 'zł',
                'format' => '1 0,00 zł',
                'exchange_rate' => 0.00,
                'flag' => 'pl.png',
            ],
            'PYG' => [
                'name' => 'Paraguay, Guarani',
                'symbol' => '₲',
                'format' => '₲ 1,0.00',
                'exchange_rate' => 0.00,
                'flag' => 'py.png',
            ],
            'QAR' => [
                'name' => 'Qatari Rial',
                'symbol' => '﷼',
                'format' => '﷼ 1,0.00',
                'exchange_rate' => 0.00,
                'flag' => 'qa.png',
            ],
            'RON' => [
                'name' => 'Romania, New Leu',
                'symbol' => 'lei',
                'format' => '1,0.00 lei',
                'exchange_rate' => 0.00,
                'flag' => 'ro.png',
            ],
            'RSD' => [
                'name' => 'Serbian Dinar',
                'symbol' => 'Дин.',
                'format' => '1,0.00 Дин.',
                'exchange_rate' => 0.00,
                'flag' => 'rs.png',
            ],
            'RUB' => [
                'name' => 'Russian Ruble',
                'symbol' => '₽',
                'format' => '1 0,00 ₽',
                'exchange_rate' => 0.00,
                'flag' => 'ru.png',
            ],
            'RWF' => [
                'name' => 'Rwanda Franc',
                'symbol' => 'RWF',
                'format' => 'RWF 1 0,00',
                'exchange_rate' => 0.00,
                'flag' => 'rw.png',
            ],
            'SAR' => [
                'name' => 'Saudi Riyal',
                'symbol' => '﷼',
                'format' => '﷼ 1,0.00',
                'exchange_rate' => 0.00,
                'flag' => 'sa.png',
            ],
            'SBD' => [
                'name' => 'Solomon Islands Dollar',
                'symbol' => '$',
                'format' => '$1,0.00',
                'exchange_rate' => 0.00,
                'flag' => 'sb.png',
            ],
            'SCR' => [
                'name' => 'Seychelles Rupee',
                'symbol' => '₨',
                'format' => '₨1,0.00',
                'exchange_rate' => 0.00,
                'flag' => 'sc.png',
            ],
            'SDD' => [
                'name' => 'Sudanese Dinar',
                'symbol' => 'LSd',
                'format' => '1,0.00LSd',
                'exchange_rate' => 0.00,
                'flag' => 'sd.png',
            ],
            'SEK' => [
                'name' => 'Swedish Krona',
                'symbol' => 'kr',
                'format' => '1 0,00 kr',
                'exchange_rate' => 0.00,
                'flag' => 'se.png',
            ],
            'SGD' => [
                'name' => 'Singapore Dollar',
                'symbol' => '$',
                'format' => '$1,0.00',
                'exchange_rate' => 0.00,
                'flag' => 'sg.png',
            ],
            'SHP' => [
                'name' => 'Saint Helena Pound',
                'symbol' => '£',
                'format' => '£1,0.00',
                'exchange_rate' => 0.00,
                'flag' => 'sh.png',
            ],
            'SLL' => [
                'name' => 'Sierra Leone, Leone',
                'symbol' => 'Le',
                'format' => 'Le1,0.00',
                'exchange_rate' => 0.00,
                'flag' => 'sl.png',
            ],
            'SOS' => [
                'name' => 'Somali Shilling',
                'symbol' => 'S',
                'format' => 'S1,0.00',
                'exchange_rate' => 0.00,
                'flag' => 'so.png',
            ],
            'SRD' => [
                'name' => 'Surinam Dollar',
                'symbol' => '$',
                'format' => '$1,0.00',
                'exchange_rate' => 0.00,
                'flag' => 'sr.png',
            ],
            'STD' => [
                'name' => 'Sao Tome and Principe, Dobra',
                'symbol' => 'Db',
                'format' => 'Db1,0.00',
                'exchange_rate' => 0.00,
                'flag' => 'st.png',
            ],
            'SVC' => [
                'name' => 'El Salvador Colon',
                'symbol' => '₡',
                'format' => '₡1,0.00',
                'exchange_rate' => 0.00,
                'flag' => 'sv.png',
            ],
            'SYP' => [
                'name' => 'Syrian Pound',
                'symbol' => '£',
                'format' => '£ 1,0.00',
                'exchange_rate' => 0.00,
                'flag' => 'sy.png',
            ],
            'SZL' => [
                'name' => 'Swaziland, Lilangeni',
                'symbol' => 'E',
                'format' => 'E1,0.00',
                'exchange_rate' => 0.00,
                'flag' => 'sz.png',
            ],
            'THB' => [
                'name' => 'Thailand, Baht',
                'symbol' => '฿',
                'format' => '฿1,0.00',
                'exchange_rate' => 0.00,
                'flag' => 'th.png',
            ],
            'TJS' => [
                'name' => 'Tajikistan, Somoni',
                'symbol' => 'TJS',
                'format' => '1 0;00 TJS',
                'exchange_rate' => 0.00,
                'flag' => 'tj.png',
            ],
            'TMT' => [
                'name' => 'Turkmenistani New Manat',
                'symbol' => 'm',
                'format' => '1 0,m',
                'exchange_rate' => 0.00,
                'flag' => 'tm.png',
            ],
            'TND' => [
                'name' => 'Tunisian Dinar',
                'symbol' => 'د.ت.‏',
                'format' => 'د.ت.‏ 1,0.000',
                'exchange_rate' => 0.00,
                'flag' => 'tn.png',
            ],
            'TOP' => [
                'name' => 'Tonga, Paanga',
                'symbol' => 'T$',
                'format' => 'T$1,0.00',
                'exchange_rate' => 0.00,
                'flag' => 'to.png',
            ],
            'TRY' => [
                'name' => 'Turkish Lira',
                'symbol' => 'TL',
                'format' => '₺1,0.00',
                'exchange_rate' => 0.00,
                'flag' => 'tr.png',
            ],
            'TTD' => [
                'name' => 'Trinidad and Tobago Dollar',
                'symbol' => 'TT$',
                'format' => 'TT$1,0.00',
                'exchange_rate' => 0.00,
                'flag' => 'tt.png',
            ],
            'TWD' => [
                'name' => 'New Taiwan Dollar',
                'symbol' => 'NT$',
                'format' => 'NT$1,0.00',
                'exchange_rate' => 0.00,
                'flag' => 'tw.png',
            ],
            'TZS' => [
                'name' => 'Tanzanian Shilling',
                'symbol' => 'TSh',
                'format' => 'TSh1,0.00',
                'exchange_rate' => 0.00,
                'flag' => 'tz.png',
            ],
            'UAH' => [
                'name' => 'Ukraine, Hryvnia',
                'symbol' => '₴',
                'format' => '1 0,00₴',
                'exchange_rate' => 0.00,
                'flag' => 'ua.png',
            ],
            'UGX' => [
                'name' => 'Uganda Shilling',
                'symbol' => 'USh',
                'format' => 'USh1,0.00',
                'exchange_rate' => 0.00,
                'flag' => 'ug.png',
            ],
            'USD' => [
                'name' => 'US Dollar',
                'symbol' => '$',
                'format' => '$1,0.00',
                'exchange_rate' => 0.00,
                'flag' => 'us.png',
            ],
            'UYU' => [
                'name' => 'Peso Uruguayo',
                'symbol' => '$U',
                'format' => '$U 1,0.00',
                'exchange_rate' => 0.00,
                'flag' => 'uy.png',
            ],
            'UZS' => [
                'name' => 'Uzbekistan Sum',
                'symbol' => 'сўм',
                'format' => '1 0,00 сўм',
                'exchange_rate' => 0.00,
                'flag' => 'uz.png',
            ],
            'VEF' => [
                'name' => 'Venezuela Bolivares Fuertes',
                'symbol' => 'Bs. F.',
                'format' => 'Bs. F. 1,0.00',
                'exchange_rate' => 0.00,
                'flag' => 've.png',
            ],
            'VND' => [
                'name' => 'Viet Nam, Dong',
                'symbol' => '₫',
                'format' => '1,0.0 ₫',
                'exchange_rate' => 0.00,
                'flag' => 'vn.png',
            ],
            'VUV' => [
                'name' => 'Vanuatu, Vatu',
                'symbol' => 'VT',
                'format' => '1,0.VT',
                'exchange_rate' => 0.00,
                'flag' => 'vu.png',
            ],
            'WST' => [
                'name' => 'Samoa, Tala',
                'symbol' => 'WS$',
                'format' => 'WS$1,0.00',
                'exchange_rate' => 0.00,
                'flag' => 'ws.png',
            ],
            'XAF' => [
                'name' => 'Franc CFA (XAF)',
                'symbol' => 'F.CFA',
                'format' => '1,0.00 F.CFA',
                'exchange_rate' => 0.00,
                'flag' => 'xa.png',
            ],
            'XCD' => [
                'name' => 'East Caribbean Dollar',
                'symbol' => '$',
                'format' => '$1,0.00',
                'exchange_rate' => 0.00,
                'flag' => 'xc.png',
            ],
            'XOF' => [
                'name' => 'Franc CFA (XOF)',
                'symbol' => 'F.CFA',
                'format' => '1,0.00 F.CFA',
                'exchange_rate' => 0.00,
                'flag' => 'xo.png',
            ],
            'XPF' => [
                'name' => 'CFP Franc',
                'symbol' => 'F',
                'format' => '1,0.00F',
                'exchange_rate' => 0.00,
                'flag' => 'xp.png',
            ],
            'YER' => [
                'name' => 'Yemeni Rial',
                'symbol' => '﷼',
                'format' => '﷼ 1,0.00',
                'exchange_rate' => 0.00,
                'flag' => 'ye.png',
            ],
            'ZAR' => [
                'name' => 'South Africa, Rand',
                'symbol' => 'R',
                'format' => 'R 1,0.00',
                'exchange_rate' => 0.00,
                'flag' => 'za.png',
            ],
            'ZMW' => [
                'name' => 'Zambia Kwacha',
                'symbol' => 'ZK',
                'format' => 'ZK1,0.00',
                'exchange_rate' => 0.00,
                'flag' => 'zm.png',
            ],
        ];

    }

    public static function currencySelectionOptions(): array
    {
        return collect(static::currencyList())
            ->map(fn (array $currency, string $code) => [
                'id' => $code,
                'name' => "{$code} - {$currency['name']}",
                'code' => $code,
            ])
            ->values()
            ->all();
    }

    public static function attributesFromListCode(string $code): array
    {
        $currency = static::currencyList()[$code] ?? [];

        return [
            'name' => $currency['name'] ?? null,
            'code' => $code,
            'symbol' => $currency['symbol'] ?? null,
            'format' => $currency['format'] ?? null,
            'exchange_rate' => $currency['exchange_rate'] ?? 0,
            'flag' => $currency['flag'] ?? null,
            'is_active' => true,
            'is_base_currency' => false,
        ];
    }
}
