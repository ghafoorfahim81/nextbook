import { nextTick } from 'vue'
import { HIGHLIGHT_MARK_CLASS, HIGHLIGHT_MARK_ON_PRIMARY_CLASS } from '@/utils/highlightSearch'

const SKIP_SELECTOR = 'input, textarea, select, script, style, [contenteditable], [data-no-highlight], [data-search-input]'
const MARK_SELECTOR = 'mark[data-search-highlight]'

function markClassForNode(textNode) {
    if (textNode.parentElement?.closest('.bg-primary')) {
        return HIGHLIGHT_MARK_ON_PRIMARY_CLASS
    }

    return HIGHLIGHT_MARK_CLASS
}

function unwrapHighlights(root) {
    root.querySelectorAll(MARK_SELECTOR).forEach((mark) => {
        const parent = mark.parentNode
        if (!parent) {
            return
        }

        parent.replaceChild(document.createTextNode(mark.textContent ?? ''), mark)
        parent.normalize()
    })
}

function highlightTextNode(textNode, query) {
    const text = textNode.textContent ?? ''
    const lowerText = text.toLowerCase()
    const lowerQuery = query.toLowerCase()

    if (!lowerText.includes(lowerQuery)) {
        return
    }

    const markClass = markClassForNode(textNode)
    const fragment = document.createDocumentFragment()
    let last = 0
    let idx = lowerText.indexOf(lowerQuery, last)

    while (idx !== -1) {
        if (idx > last) {
            fragment.appendChild(document.createTextNode(text.slice(last, idx)))
        }

        const mark = document.createElement('mark')
        mark.dataset.searchHighlight = 'true'
        mark.className = markClass
        mark.textContent = text.slice(idx, idx + query.length)
        fragment.appendChild(mark)

        last = idx + query.length
        idx = lowerText.indexOf(lowerQuery, last)
    }

    if (last < text.length) {
        fragment.appendChild(document.createTextNode(text.slice(last)))
    }

    textNode.parentNode?.replaceChild(fragment, textNode)
}

function collectTextNodes(root) {
    const nodes = []
    const walker = document.createTreeWalker(root, NodeFilter.SHOW_TEXT, {
        acceptNode(node) {
            const parent = node.parentElement
            if (!parent || parent.closest(MARK_SELECTOR)) {
                return NodeFilter.FILTER_REJECT
            }

            if (parent.closest(SKIP_SELECTOR)) {
                return NodeFilter.FILTER_REJECT
            }

            if (!node.textContent?.trim()) {
                return NodeFilter.FILTER_REJECT
            }

            return NodeFilter.FILTER_ACCEPT
        },
    })

    while (walker.nextNode()) {
        nodes.push(walker.currentNode)
    }

    return nodes
}

function resolveSearchQuery(value) {
    if (value && typeof value === 'object' && !Array.isArray(value)) {
        return String(value.query ?? '').trim()
    }

    return String(value ?? '').trim()
}

function applyHighlight(root, value) {
    if (!root) {
        return
    }

    unwrapHighlights(root)

    const normalizedQuery = resolveSearchQuery(value)
    if (!normalizedQuery) {
        return
    }

    const textNodes = collectTextNodes(root)
    textNodes.forEach((node) => highlightTextNode(node, normalizedQuery))
}

export const vHighlightSearch = {
    mounted(el, binding) {
        nextTick(() => applyHighlight(el, binding.value))
    },
    updated(el, binding) {
        nextTick(() => applyHighlight(el, binding.value))
    },
}

export default vHighlightSearch
