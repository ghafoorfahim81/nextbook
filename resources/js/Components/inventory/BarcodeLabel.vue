<script setup>
import JsBarcode from 'jsbarcode'
import { computed, onMounted, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'

const props = defineProps({
  item: { type: Object, required: true },
  size: { type: Object, required: true },
  showName: { type: Boolean, default: true },
  showCode: { type: Boolean, default: true },
  showPrice: { type: Boolean, default: true },
  currencySymbol: { type: String, default: '' },
  decimalPlaces: { type: Number, default: 2 },
})

const { t } = useI18n()
const svgRef = ref(null)

const rootStyle = computed(() => ({
  width: `${props.size.widthMm}mm`,
  minHeight: `${props.size.heightMm}mm`,
  padding: `${props.size.paddingMm}mm`,
  display: 'flex',
  flexDirection: 'column',
  alignItems: 'center',
  justifyContent: 'flex-start',
  overflow: 'hidden',
  pageBreakInside: 'avoid',
  breakInside: 'avoid',
  background: 'white',
  color: 'black',
}))

const nameStyle = computed(() => ({
  fontSize: `${props.size.titleFontPx}px`,
  lineHeight: 1.1,
  fontWeight: '500',
  textAlign: 'center',
  width: '100%',
  marginBottom: '1.25mm',
  whiteSpace: 'nowrap',
  overflow: 'hidden',
  textOverflow: 'ellipsis',
}))

const codeStyle = computed(() => ({
  fontSize: `${props.size.textFontPx}px`,
  lineHeight: 1.1,
  textAlign: 'center',
  width: '100%',
  marginTop: '1mm',
  whiteSpace: 'nowrap',
  overflow: 'hidden',
  textOverflow: 'ellipsis',
}))

const priceStyle = computed(() => ({
  fontSize: `${props.size.textFontPx}px`,
  lineHeight: 1.1,
  textAlign: 'center',
  width: '100%',
  marginTop: '0.8mm',
  whiteSpace: 'nowrap',
  overflow: 'hidden',
  textOverflow: 'ellipsis',
}))

const barcodeValue = computed(() => String(props.item?.barcode ?? '').trim())
const showItemName = computed(() => props.showName && Boolean(String(props.item?.name ?? '').trim()))
const showItemCode = computed(() => props.showCode && Boolean(String(props.item?.code ?? '').trim()))

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
  if (!svgRef.value || !barcodeValue.value) return

  JsBarcode(svgRef.value, barcodeValue.value, {
    format: 'CODE128',
    displayValue: false,
    background: 'transparent',
    margin: 0,
    height: props.size.barcodeHeightPx,
    width: props.size.barWidth,
  })
}

onMounted(renderBarcode)

watch(
  () => [barcodeValue.value, props.size.id],
  () => renderBarcode(),
)
</script>

<template>
  <div class="barcode-label" :style="rootStyle">
    <div v-if="showItemName" :style="nameStyle">
      {{ item.name }}
    </div>

    <svg
      ref="svgRef"
      :style="{ width: '100%', height: `${size.svgHeightPx}px`, display: 'block' }"
    />

    <div v-if="showItemCode" :style="codeStyle">
      {{ item.code }}
    </div>

    <div v-if="showPrice && formattedPrice" :style="priceStyle">
      {{ formattedPrice }}
    </div>
  </div>
</template>
