// The dashboard dialogs (the testimonial import and the account delete) open
// in a native <dialog>. The trigger slot opens it. No event bus is necessary.
import { test } from 'node:test'
import assert from 'node:assert/strict'
import { readFile } from 'node:fs/promises'
import * as vue from 'vue'
import { parse, compileTemplate } from 'vue/compiler-sfc'
import { renderToString } from 'vue/server-renderer'

const source = await readFile(new URL('../../resources/js/components/Modal.vue', import.meta.url), 'utf8')

async function renderModal(props) {
  const { descriptor } = parse(source)
  const { code } = compileTemplate({
    source: descriptor.template.content,
    id: 'modal',
    compilerOptions: { mode: 'function' },
  })

  const render = new Function('Vue', code)(vue)
  const modal = {
    props: ['labelledby', 'openOnLoad', 'maxWidth'],
    setup: () => ({ dialog: null, maxWidthClass: 'max-w-lg', open: () => {}, close: () => {}, closeOnBackdrop: () => {} }),
    render,
  }

  return renderToString(vue.createSSRApp({
    render: () => vue.h(modal, props, {
      trigger: ({ open }) => vue.h('button', { type: 'button', 'data-open': typeof open }, 'Import'),
      default: ({ close }) => vue.h('form', { 'data-close': typeof close }, 'Body'),
    }),
  }))
}

test('the trigger slot gets an open function and sits outside the dialog', async () => {
  const html = await renderModal({ labelledby: 'import-title' })

  assert.match(html, /<button type="button" data-open="function">Import<\/button>/)
  assert.ok(html.indexOf('data-open') < html.indexOf('<dialog'))
})

test('the dialog is named by its title and gives the content a close function', async () => {
  const html = await renderModal({ labelledby: 'import-title' })

  assert.match(html, /<dialog aria-labelledby="import-title"/)
  assert.match(html, /backdrop:bg-black\/70/)
  assert.match(html, /<form data-close="function">Body<\/form>(<!--\]-->)?<\/dialog>/)
})

test('the modal uses the native dialog, not Headless UI or a global event bus', () => {
  assert.doesNotMatch(source, /@headlessui\/vue/)
  assert.doesNotMatch(source, /window\.emitter/)
})
