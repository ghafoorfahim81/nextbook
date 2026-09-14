<script setup>
import JsBarcode from 'jsbarcode'
import { computed, onMounted, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'

const props = defineProps({
  item: { type: Object, required: true },
  /** Resolved geometry for one label: millimetre box plus derived pixel sizes. */
  size: { type: Object, required: true },
  /** A JsBarcode format id — CODE128, EAN13, UPC, CODE39… */
  symbology: { type: String, default: 'CODE128' },
  showName: { type: Boolean, default: true },
  showVariant: { type: Boolean, default: true },
  showCode: { type: Boolean, default: true },
  showPrice: { type: Boolean, default: true },
  showBarcode: { type: Boolean, default: true },
  showBorder: { type: Boolean, default: false },
  currencySymbol: { type: String, default: '' },
  decimalPlaces: { type: Number, default: 2 },
})

const { t } = useI18n()
const svgRef = ref(null)

// A symbology rejects data it cannot encode — EAN-13 wants twelve digits, UPC
// eleven. The label says so rather than silently printing a blank strip.
const renderFailed = ref(false)

const rootStyle = computed(() => ({
  width: `${props.size.widthMm}mm`,
  height: `${props.size.heightMm}mm`,
  padding: `${props.size.paddingMm}mm`,
  display: 'flex',
  flexDirection: 'column',
  alignItems: 'center',
  justifyContent: 'center',
  overflow: 'hidden',
  pageBreakInside: 'avoid',
  breakInside: 'avoid',
  background: 'white',
  color: 'black',
  border: props.showBorder ? '0.2mm solid black' : 'none',
}))

const textStyle = (fontPx, { bold = false, marginTop = 0, marginBottom = 0 } = {}) => ({
  fontSize: `${fontPx}px`,
  lineHeight: 1.15,
  fontWeight: bold ? '600' : '400',
  textAlign: 'center',
  width: '100%',
  marginTop: `${marginTop}mm`,
  marginBottom: `${marginBottom}mm`,
  whiteSpace: 'nowrap',
  overflow: 'hidden',
  textOverflow: 'ellipsis',
})

const nameStyle = computed(() => textStyle(props.size.titleFontPx, { bold: true, marginBottom: 0.6 }))
const variantStyle = computed(() => textStyle(props.size.textFontPx, { bold: true, marginBottom: 0.5 }))
const codeStyle = computed(() => textStyle(props.size.textFontPx, { marginTop: 0.5 }))
const priceStyle = computed(() => textStyle(props.size.textFontPx, { bold: true, marginTop: 0.4 }))
const errorStyle = computed(() => ({ ...textStyle(props.size.textFontPx), color: '#b91c1c', whiteSpace: 'normal' }))

const barcodeValue = computed(() => String(props.item?.barcode ?? '').trim())
const showItemName = computed(() => props.showName && Boolean(String(props.item?.name ?? '').trim()))
const showItemCode = computed(() => props.showCode && Boolean(String(props.item?.code ?? '').trim()))

// Which variant this label is for ("Red / L"). Blank for a plain product, whose
// single default variant is the item itself and needs no second line.
const variantLabel = computed(() => String(props.item?.variant_label ?? '').trim())
const showItemVariant = computed(() => props.showVariant && Boolean(variantLabel.value))

const formattedPrice = computed(() => {
  const value = Number(props.item?.sale_price ?? 0)
  if (!Number.isFinite(value)) return null

  const output = value.toLocaleString(undefined, {
    minimumFractionDigits: props.decimalPlaces,
    maximumFractionDigits: props.decimalPlaces,
  })

  const amount = props.currencySymbol ? `${output} ${props.currencySymbol}` : output
  return `${t('general.price')}: ${amount}`
})

function renderBarcode() {
  renderFailed.value = false

  if (!props.showBarcode || !svgRef.value || !barcodeValue.value) return

  try {
    JsBarcode(svgRef.value, barcodeValue.value, {
      format: props.symbology || 'CODE128',
      displayValue: false,
      background: 'transparent',
      margin: 0,
      height: props.size.barcodeHeightPx,
      width: props.size.barWidth,
      // Without this JsBarcode swallows the problem and leaves whatever was
      // drawn before, so a bad code would print as the previous item's bars.
      valid: (isValid) => { renderFailed.value = !isValid },
    })
  } catch (e) {
    renderFailed.value = true
  }

  if (renderFailed.value && svgRef.value) {
    svgRef.value.innerHTML = ''
  }
}

onMounted(renderBarcode)

watch(
  () => [
    barcodeValue.value,
    props.symbology,
    props.showBarcode,
    props.size.barcodeHeightPx,
    props.size.barWidth,
  ],
  renderBarcode,
)
</script>

<template>
  <div class="barcode-label" :style="rootStyle">
    <div v-if="showItemName" :style="nameStyle">
      {{ item.name }}
    </div>

    <div v-if="showItemVariant" :style="variantStyle">
      {{ variantLabel }}
    </div>

    <svg
      v-show="showBarcode && !renderFailed"
      ref="svgRef"
      :style="{ width: '100%', height: `${size.svgHeightPx}px`, display: 'block' }"
    />

    <div v-if="showBarcode && renderFailed" :style="errorStyle">
      {{ t('item.barcode_invalid_for_symbology', { symbology }) }}
    </div>

    <div v-if="showItemCode" :style="codeStyle">
      {{ item.code }}
    </div>

    <div v-if="showPrice && formattedPrice" :style="priceStyle">
      {{ formattedPrice }}
    </div>
  </div>
</template>
