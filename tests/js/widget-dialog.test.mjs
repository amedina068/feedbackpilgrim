// The release notes and testimonial widgets open in a native <dialog>.
// The dialog has a title, which names it, and a close button.
import { test } from 'node:test'
import assert from 'node:assert/strict'
import { readFile } from 'node:fs/promises'
import * as vue from 'vue'
import { parse, compileTemplate } from 'vue/compiler-sfc'
import { renderToString } from 'vue/server-renderer'

const component = name => readFile(new URL(`../../resources/js/components/${name}.vue`, import.meta.url), 'utf8')

async function renderDialog(props) {
  const { descriptor } = parse(await component('WidgetDialog'))
  const { code } = compileTemplate({
    source: descriptor.template.content,
    id: 'widget-dialog',
    compilerOptions: { mode: 'function' },
  })

  const render = new Function('Vue', code)(vue)
  const dialog = {
    props: ['open', 'panel'],
    setup: () => ({ dialog: null, titleId: 'widget-title', XMarkIcon: 'svg', closeOnBackdrop: () => {}, onNativeClose: () => {} }),
    render,
  }

  return renderToString(vue.createSSRApp({
    render: () => vue.h(dialog, props, { title: () => 'Latest release notes', default: () => vue.h('p', 'Body') }),
  }))
}

test('the dialog is named by its title and has a close button', async () => {
  const html = await renderDialog({ open: false, panel: false })

  assert.match(html, /^<dialog aria-labelledby="widget-title"/)
  assert.match(html, /<h2 id="widget-title"[^>]*>(<!--\[-->)?Latest release notes(<!--\]-->)?<\/h2>/)
  assert.match(html, /<span class="sr-only">Close<\/span>/)
  assert.match(html, /<p>Body<\/p>/)
  assert.match(html, /max-w-lg rounded-lg/)
})

test('the panel variant fills the height of the window on the right side', async () => {
  const html = await renderDialog({ open: false, panel: true })

  assert.match(html, /right-0 left-auto m-0 h-dvh max-h-none w-full max-w-md/)
})

test('the widgets use the native dialog, not Headless UI', async () => {
  for (const name of ['ReleaseNotesModal', 'TestimonialsModal']) {
    const source = await component(name)

    assert.match(source, /<WidgetDialog /, name)
    assert.doesNotMatch(source, /@headlessui\/vue/, name)
  }
})
