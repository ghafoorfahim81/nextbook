/**
 * Print a server-rendered document without leaving or opening a new page.
 * The dedicated print view still loads (invoice/voucher layout) inside a
 * hidden iframe so the current screen stays put.
 */
export function printDocument(url) {
    if (!url || typeof document === 'undefined') {
        return
    }

    document.getElementById('nextbook-print-frame')?.remove()

    const iframe = document.createElement('iframe')
    iframe.id = 'nextbook-print-frame'
    iframe.setAttribute('aria-hidden', 'true')
    iframe.setAttribute('tabindex', '-1')
    iframe.style.cssText = 'position:fixed;left:-10000px;top:0;width:1024px;height:768px;border:0;'
    iframe.src = url

    const cleanup = () => {
        iframe.remove()
    }

    iframe.addEventListener('load', () => {
        const win = iframe.contentWindow
        if (!win) {
            cleanup()
            return
        }

        win.addEventListener('afterprint', cleanup)
        window.setTimeout(cleanup, 180000)
    })

    document.body.appendChild(iframe)
}
