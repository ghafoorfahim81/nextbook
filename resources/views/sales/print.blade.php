<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>فاکتور #{{ $sale->number }}</title>
    <style>
        @font-face {
            font-family: 'Nazanin';
            src: url('{{ public_path('fonts/Nazanin.ttf') }}') format('truetype');
            font-weight: normal;
            font-style: normal;
        }
        body {
            margin: 0;
            padding: 16px;
            font-family: 'Nazanin', 'DejaVu Sans', 'Tahoma', sans-serif;
            background: linear-gradient(180deg, #e8f3ff 0%, #f4f7fb 100%);
            color: #1e293b;
            line-height: 1.7;
        }

        .invoice-shell {
            max-width: 900px;
            margin: 0 auto;
            background: #ffffff;
            border-radius: 18px;
            box-shadow: 0 10px 40px rgba(15, 23, 42, 0.12);
            overflow: hidden;
        }

        .top {
            padding: 22px 26px 10px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            flex-wrap: nowrap;
        }

        .brand {
            display: flex;
            align-items: center;
            gap: 12px;
            flex: 1;
        }

        .brand-icon {
            width: 54px;
            height: 54px;
            border-radius: 12px;
            background: linear-gradient(135deg, #1e73be, #2d9df4);
            color: #ffffff;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-weight: 800;
            font-size: 22px;
            letter-spacing: 0.5px;
        }

        .brand-text { display: flex; flex-direction: column; gap: 2px; }
        .brand-name { font-size: 20px; font-weight: 800; margin: 0; color: #0f172a; }
        .brand-tagline { font-size: 12px; color: #64748b; }

        .invoice-title { text-align: right; white-space: nowrap; flex: 0 0 auto; }

        .invoice-label {
            font-size: 28px;
            font-weight: 800;
            color: #2d7de4;
            letter-spacing: 1px;
        }

        .website {
            margin-top: 4px;
            font-size: 12px;
            color: #475569;
        }

        .divider {
            height: 1px;
            margin: 6px 26px 0;
            background: linear-gradient(90deg, #1e73be 0%, #2d9df4 100%);
        }

        .info-row {
            display: flex;
            justify-content: space-between;
            gap: 20px;
            padding: 16px 26px 10px;
        }

        .info-block {
            flex: 1;
        }

        .muted {
            color: #64748b;
            font-size: 12px;
            margin-bottom: 4px;
        }

        .name-bold {
            font-weight: 800;
            font-size: 17px;
            color: #0f172a;
            margin-bottom: 6px;
        }

        .detail-line {
            color: #334155;
            font-size: 13px;
        }

        .invoice-meta { min-width: 240px; text-align: right; }

        .meta-line {
            display: grid;
            grid-template-columns: auto 1fr;
            justify-content: end;
            align-items: center;
            gap: 8px;
            color: #0f172a;
            font-weight: 700;
            font-size: 13px;
        }
        .meta-label { color: #64748b; font-weight: 600; }

        .pill {
            display: inline-block;
            padding: 2px 10px;
            border-radius: 999px;
            background: #e2e8f0;
            color: #0f172a;
            font-size: 11px;
            font-weight: 700;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin: 12px 0 0;
        }

        th {
            background: #2d7de4;
            color: #ffffff;
            text-align: center;
            padding: 10px 8px;
            font-size: 12px;
            letter-spacing: 0.2px;
        }

        td {
            border: 1px solid #dbe5f3;
            padding: 9px 10px;
            font-size: 12px;
            text-align: center;
            color: #0f172a;
        }

        tbody tr:nth-child(even) { background: #f8fbff; }

        .row-table { direction: rtl; }
        .row-table td:first-child { width: 40px; }
        .row-table td:nth-child(2) { text-align: right; }

        .grid {
            display: flex;
            gap: 20px;
            padding: 18px 26px 22px;
            align-items: stretch;
        }

        .card {
            flex: 1;
            border: 1px solid #dbe5f3;
            border-radius: 10px;
            padding: 14px 16px;
            background: #f8fbff;
        }

        .card h4 {
            margin: 0 0 8px;
            font-size: 13px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            color: #1e293b;
        }

        .totals-card {
            flex: 0.9;
            background: #ffffff;
            box-shadow: 0 6px 18px rgba(15, 23, 42, 0.08);
        }

        .totals-card table {
            margin: 0;
        }

        .totals-card td {
            border: none;
            padding: 8px 10px;
            font-size: 12px;
        }

        .totals-card tr + tr td { border-top: 1px solid #e2e8f0; }
        .totals-label { text-align: left; color: #475569; }
        .totals-value { text-align: right; font-weight: 700; }

        .grand-row {
            background: #2d7de4;
            color: #ffffff;
            border-radius: 8px;
            padding: 10px 12px;
            font-weight: 800;
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-top: 8px;
        }

        .notes { padding: 0 26px 22px; }

        .thanks {
            font-size: 16px;
            font-weight: 800;
            color: #1e293b;
            margin: 0 0 10px;
        }

        .terms {
            font-size: 12px;
            color: #475569;
            margin: 0;
        }

        .contact-row {
            display: flex;
            gap: 20px;
            margin-top: 12px;
            flex-wrap: wrap;
            color: #334155;
            font-size: 12px;
        }

        .contact-pill {
            padding: 8px 12px;
            border-radius: 999px;
            border: 1px solid #dbe5f3;
            background: #f8fbff;
        }

        .rtl { direction: rtl; text-align: right; }
        .ltr { direction: ltr; text-align: left; }
    </style>
</head>
<body class="rtl">
@php
    $companyName = $company->name_en ?? $company->name_fa ?? config('app.name');
    // business_type is already cast to the BusinessType enum on the Company model
    $businessType = $company->business_type?->getLabel();
    $companyTagline = $businessType ?: ($company->invoice_description ?: 'کسب‌وکار');
    $companyLogo = $company->logo_url ?? null;
    $website = $company->website ?? config('app.url');
    $currency = $sale->transaction?->currency?->code ?? $sale->transaction?->currency?->name ?? '';
    $storeName = $sale->stockOuts[0]->store->name ?? '-';
    $preferences = auth()->user()?->preferences ?? [];
    $terms = data_get($preferences, 'sales.terms') ?: ($company->invoice_description ?? '');

    $statusLabel = __($sale->status) !== $sale->status ? __($sale->status) : ucfirst($sale->status);
    $itemsSubtotal = $sale->items->sum(fn($item) => $item->quantity * $item->unit_price);
    $itemsDiscount = $sale->items->sum(fn($item) => $item->discount ?? 0);
    $itemsTax = $sale->items->sum(fn($item) => $item->tax ?? 0);
    $globalDiscount = $sale->discount ?? 0;
    $discountTotal = $itemsDiscount + $globalDiscount;
    $grandTotal = $itemsSubtotal - $discountTotal + $itemsTax;
    $transactionAmount = $sale->transaction?->amount;
@endphp

<div class="invoice-shell">
    <div class="top">
        <div class="brand">
            @if($companyLogo)
                <img src="{{ $companyLogo }}" alt="logo" style="width:54px;height:54px;border-radius:12px;object-fit:contain;border:1px solid #e2e8f0;">
            @else
                <div class="brand-icon">{{ mb_substr($companyName, 0, 1) }}</div>
            @endif
            <div class="brand-text">
                <div class="brand-name">{{ $companyName }}</div>
                <div class="brand-tagline">{{ $companyTagline }}</div>
            </div>
        </div>
        <div class="invoice-title">
            <div class="invoice-label">فاکتور</div>
            <div class="website">{{ $website }}</div>
        </div>
    </div>

    <div class="divider"></div>

    <div class="info-row">
        <div class="info-block">
            <div class="muted">صورتحساب به :</div>
            <div class="name-bold">{{ $sale->customer?->name ?? '-' }}</div>
            @if($sale->customer?->phone)<div class="detail-line">{{ $sale->customer->phone }}</div>@endif
            @if($sale->customer?->email)<div class="detail-line">{{ $sale->customer->email }}</div>@endif
            @if($sale->customer?->address)<div class="detail-line">{{ $sale->customer->address }}</div>@endif
        </div>
        <div class="invoice-meta">
            <div class="meta-line"><span class="meta-label">شماره فاکتور :</span><span class="ltr">INV-{{ $sale->number }}</span></div>
            <div class="meta-line"><span class="meta-label">تاریخ :</span><span class="ltr">{{ $sale->date?->format('d M Y') }}</span></div>
            @if($company?->phone)<div class="meta-line"><span class="meta-label">تلفن :</span><span class="ltr">{{ $company->phone }}</span></div>@endif
            @if($company?->email)<div class="meta-line"><span class="meta-label">ایمیل :</span><span class="ltr">{{ $company->email }}</span></div>@endif
            <div class="meta-line"><span class="meta-label">انبار :</span><span>{{ $storeName }}</span></div>
            <div class="meta-line"><span class="meta-label">وضعیت :</span><span class="pill">{{ $statusLabel }}</span></div>
        </div>
    </div>

    <div style="padding: 0 30px 6px;">
        <table class="row-table">
            <thead>
            <tr>
                <th>ردیف</th>
                <th>شرح</th>
                <th>تعداد</th>
                <th>قیمت</th>
                <th>جمع</th>
            </tr>
            </thead>
            <tbody>
            @foreach($sale->items as $item)
                @php
                    $lineSubtotal = $item->quantity * $item->unit_price;
                    $lineTotal = $lineSubtotal - ($item->discount ?? 0) + ($item->tax ?? 0);
                @endphp
                <tr>
                    <td class="ltr">{{ $loop->iteration }}</td>
                    <td style="text-align: right;">{{ $item->item?->name ?? '-' }}</td>
                    <td class="ltr">{{ number_format((float) $item->quantity, 2) }}</td>
                    <td class="ltr">{{ number_format((float) $item->unit_price, 2) }} {{ $currency }}</td>
                    <td class="ltr">{{ number_format((float) $lineTotal, 2) }} {{ $currency }}</td>
                </tr>
            @endforeach
            </tbody>
        </table>
    </div>

    <div class="grid">
        <div class="card">
            <h4>روش پرداخت :</h4>
            <div class="detail-line">نام بانک : {{ data_get($company, 'bank_name', '—') }}</div>
            <div class="detail-line">شماره حساب : {{ data_get($company, 'account_number', '—') }}</div>
        </div>
        <div class="card totals-card">
            <table>
                <tr>
                    <td class="totals-label">جمع جزء :</td>
                    <td class="totals-value">{{ number_format((float) $itemsSubtotal, 2) }} {{ $currency }}</td>
                </tr>
                @if($discountTotal > 0)
                <tr>
                    <td class="totals-label">تخفیف :</td>
                    <td class="totals-value">-{{ number_format((float) $discountTotal, 2) }} {{ $currency }}</td>
                </tr>
                @endif
                @if($itemsTax > 0)
                <tr>
                    <td class="totals-label">مالیات :</td>
                    <td class="totals-value">{{ number_format((float) $itemsTax, 2) }} {{ $currency }}</td>
                </tr>
                @endif
                @if($transactionAmount)
                <tr>
                    <td class="totals-label">تراکنش :</td>
                    <td class="totals-value">{{ number_format((float) $transactionAmount, 2) }} {{ $currency }}</td>
                </tr>
                @endif
            </table>
            <div class="grand-row">
                <span>جمع کل :</span>
                <span>{{ number_format((float) $grandTotal, 2) }} {{ $currency }}</span>
            </div>
        </div>
    </div>

    <div class="notes">
        <div class="thanks">از خرید شما سپاسگزاریم!</div>
        <p class="terms">
            {{ $terms ?: 'لطفاً مبلغ این فاکتور را حداکثر ظرف ۳۰ روز پرداخت کنید.' }}
        </p>
        <div class="contact-row">
            @if($company?->phone)<span class="contact-pill ltr">{{ $company->phone }}</span>@endif
            @if($company?->email)<span class="contact-pill ltr">{{ $company->email }}</span>@endif
            @if($website)<span class="contact-pill ltr">{{ $website }}</span>@endif
            @if($company?->address)<span class="contact-pill">{{ $company->address }}</span>@endif
        </div>
    </div>
</div>
</body>
</html>
