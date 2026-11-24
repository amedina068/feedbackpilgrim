import hljs from 'highlight.js/lib/core'
import bash from 'highlight.js/lib/languages/bash'
import javascript from 'highlight.js/lib/languages/javascript'
import json from 'highlight.js/lib/languages/json'
import php from 'highlight.js/lib/languages/php'
import python from 'highlight.js/lib/languages/python'
import ruby from 'highlight.js/lib/languages/ruby'
import 'highlight.js/styles/github-dark.css'

hljs.registerLanguage('bash', bash)
hljs.registerLanguage('javascript', javascript)
hljs.registerLanguage('json', json)
hljs.registerLanguage('php', php)
hljs.registerLanguage('python', python)
hljs.registerLanguage('ruby', ruby)

const COPIED_RESET_MS = 1500

function highlightAll() {
    document.querySelectorAll('pre code[class^="language-"]').forEach((el) => {
        hljs.highlightElement(el)
    })
}

function setLanguage(lang) {
    document.querySelectorAll('[data-code-card] .code-tab').forEach((tab) => {
        const active = tab.dataset.lang === lang
        tab.classList.toggle('border-gray-300', active)
        tab.classList.toggle('text-gray-100', active)
        tab.classList.toggle('border-transparent', !active)
        tab.classList.toggle('text-gray-500', !active)
    })

    document.querySelectorAll('[data-code-card] .code-pane[data-lang]').forEach((pane) => {
        pane.classList.toggle('hidden', pane.dataset.lang !== lang)
    })
}

function handleClick(event) {
    const tab = event.target.closest('.code-tab')
    if (tab) {
        setLanguage(tab.dataset.lang)
        return
    }

    const copy = event.target.closest('.copy-btn')
    if (!copy) {
        return
    }

    const card = copy.closest('[data-code-card]')
    const pane = card && card.querySelector('.code-pane:not(.hidden)')
    if (!pane) {
        return
    }

    navigator.clipboard.writeText(pane.innerText.trim()).then(() => {
        const previous = copy.textContent
        copy.textContent = 'Copied'
        setTimeout(() => {
            copy.textContent = previous
        }, COPIED_RESET_MS)
    })
}

document.addEventListener('DOMContentLoaded', () => {
    highlightAll()
    document.addEventListener('click', handleClick)
})
