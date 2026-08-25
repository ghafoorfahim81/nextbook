import { onBeforeUnmount, ref, watch } from 'vue'

const DEFAULT_DURATION = 900

// Fast at the start and settling at the end, so the final figure is readable
// well before the animation actually finishes.
function easeOutCubic(progress) {
  return 1 - Math.pow(1 - progress, 3)
}

function prefersReducedMotion() {
  return typeof window !== 'undefined'
    && typeof window.matchMedia === 'function'
    && window.matchMedia('(prefers-reduced-motion: reduce)').matches
}

/**
 * Counts a figure up to its real value on load, and re-runs from whatever is on
 * screen whenever the value changes — so a dashboard refresh animates from the
 * old number to the new one rather than dropping back to zero.
 *
 * `source` is a ref or a getter. The returned ref holds the in-between value;
 * callers format it themselves, since a money tile and a count tile round
 * differently.
 */
export function useCountUp(source, options = {}) {
  const duration = options.duration ?? DEFAULT_DURATION
  const displayed = ref(0)

  let frame = null

  function stop() {
    if (frame !== null) {
      cancelAnimationFrame(frame)
      frame = null
    }
  }

  function animateTo(target) {
    stop()

    const to = Number(target || 0)

    // A figure nobody asked to see move, or one that is not a number, is simply
    // set. Honouring the reduced-motion setting matters more than the flourish.
    if (!Number.isFinite(to) || prefersReducedMotion() || typeof requestAnimationFrame !== 'function') {
      displayed.value = Number.isFinite(to) ? to : 0
      return
    }

    const from = displayed.value

    if (from === to) return

    const started = performance.now()

    const step = (now) => {
      const progress = Math.min((now - started) / duration, 1)

      if (progress >= 1) {
        // Land on the exact target: the eased value would otherwise leave a
        // fraction of a cent behind.
        displayed.value = to
        frame = null
        return
      }

      displayed.value = from + (to - from) * easeOutCubic(progress)
      frame = requestAnimationFrame(step)
    }

    frame = requestAnimationFrame(step)
  }

  watch(source, animateTo, { immediate: true })
  onBeforeUnmount(stop)

  return displayed
}
