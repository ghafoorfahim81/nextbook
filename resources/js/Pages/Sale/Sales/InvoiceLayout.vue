<script setup>
import { computed } from 'vue'

const props = defineProps({ invoice: Object, company: Object })

const formattedDate = computed(() => {
  if (!props.invoice?.date) return ''
  return new Date(props.invoice.date).toLocaleDateString('fa-IR')
})

const formatNumber = (val) => {
  if (val === null || val === undefined || val === '') return ''
  const n = Number(val)
  if (Number.isNaN(n)) return String(val)
  return n.toLocaleString('fa-IR')
}

// Keep the first page visually "full" by adding empty rows.
// Chosen to match A4 layout with header/meta/customer/totals/footer blocks.
const MIN_TABLE_ROWS = 12
const emptyRowsCount = computed(() => {
  const count = props.invoice?.items?.length ?? 0
  return Math.max(0, MIN_TABLE_ROWS - count)
})
</script>

<template>
  <div class="invoice">

    <!-- HEADER -->
    <header class="invoice-header">
      <div class="header-brand">
        <img src="/images/hajizada_logo.jpeg" alt="Logo" class="logo" />
        <h1 class="company-name">{{ company.name_fa }}</h1>
      </div>
    </header>

    <div class="color-bar">
      <span class="red"></span>
      <span class="blue"></span>
    </div>

    <!-- INVOICE META -->
    <section class="invoice-meta">
      <div class="meta-right">{{ company.name_fa }}</div>
      <div class="meta-center">فاکتور فروش</div>
      <div class="meta-center">
        <div><strong>شماره فاکتور:</strong> {{ invoice.number }}</div>
        <div><strong>تاریخ فاکتور:</strong> {{ formattedDate }}</div>
      </div>
    </section>

    <!-- CUSTOMER INFO -->
    <section class="customer-info">
      <div class="section-title">مشخصات خریدار</div>
      <div class="info-row">
        <span class="info-label">نام شخص حقیقی / حقوقی:</span>
        <span class="info-value">{{ invoice.customer?.name }}</span>
      </div>
      <div class="info-row">
        <span class="info-label">نوع فاکتور:</span>
        <span class="info-value">افغانی</span>
      </div>
    </section>

    <div class="invoice-body">
      <!-- ITEMS TABLE -->
      <table class="items-table">
        <thead>
          <tr>
            <th class="col-row">ردیف</th>
            <th class="col-item">کالا</th>
            <th class="col-store">کد انبار</th>
            <th class="col-qty">تعداد</th>
            <th class="col-unit">فی</th>
            <th class="col-amount">مبلغ</th>
            <th class="col-discount">تخفیف</th>
            <th class="col-total">جمع کل</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="(item, i) in invoice.items" :key="i">
            <td>{{ i + 1 }}</td>
            <td >{{ item.item_name }}</td>
            <td>{{ invoice.store?.name ?? '-' }}</td>
            <td>{{ formatNumber(item.quantity) }}</td>
            <td>{{ formatNumber(item.unit_price) }}</td>
            <td>{{ formatNumber(item.subtotal) }}</td>
            <td>{{ formatNumber(item.discount ?? 0) }}</td>
            <td>{{ formatNumber((item.unit_price * item.quantity) - (item.discount ?? 0) + (item.tax ?? 0)) }}</td>
          </tr>

          <!-- Filler rows to keep first page full -->
          <tr v-for="n in emptyRowsCount" :key="`empty-${n}`" class="filler-row">
            <td>&nbsp;</td>
            <td class="text-right">&nbsp;</td>
            <td>&nbsp;</td>
            <td>&nbsp;</td>
            <td>&nbsp;</td>
            <td>&nbsp;</td>
            <td>&nbsp;</td>
            <td>&nbsp;</td>
          </tr>

          <!-- Totals row (like the sample invoice) -->
          <tr class="items-total-row">
            <td colspan="4" class="items-total-label">جمع مبلغ</td>
            <td colspan="4" class="items-total-value">{{ formatNumber(invoice.subtotal) }}</td>
          </tr>
        </tbody>
      </table>

      <!-- SETTLEMENT (bottom small table in sample) -->
      <section class="settlement">
        <table class="settlement-table">
          <tbody>
            <tr class="settlement-entry">
              <td class="settlement-rowno">1</td>
              <td class="settlement-date">{{ formattedDate }}</td>
              <td class="settlement-paytype">نقدی</td>
              <td class="settlement-desc">دخل نقدی افغانی دفتر</td>
              <td class="settlement-amount">{{ formatNumber(invoice.total) }}</td>
            </tr>
            <tr class="settlement-sum">
              <td colspan="4" class="settlement-sum-label">جمع تسویه</td>
              <td class="settlement-sum-amount">{{ formatNumber(invoice.total) }}</td>
            </tr>
          </tbody>
        </table>
      </section>

      <!-- FOOTER NOTES -->
      <section class="notes">
        <div class="notes-title">توضیحات:</div>
        <div class="notes-box"></div>
      </section>

      <!-- SIGNATURES -->
      <section class="signatures">
        <div class="signature">امضاء مدیر فروش</div>
        <div class="signature center">بدون مهر و امضاء اعتبار ندارد</div>
        <div class="signature">امضاء خزانه دار</div>
      </section>

      <!-- FOOTER -->
      <footer class="invoice-footer">
        <div class="footer-rule"></div>
        <div class="address">
          آدرس دفتر مرکزی: هرات، جاده‌ی بهزاد، روبروی مارکت بهزاد
        </div>
        <div class="footer-bar">
          <span class="red"></span>
          <span class="blue"></span>
        </div>
        <div class="contact">
          <span class="contact-website">{{ company.website }}</span>
          <span class="contact-sep"> | </span>
          <span class="contact-email">{{ company.email }}</span>
          <span class="contact-sep"> | </span>
          <span class="contact-phone">{{ company.phone }}</span>
        </div>
      </footer>
    </div>

  </div>
</template>
