// Vue compiles the server HTML inside #app as a template. Blade escapes user
// text but keeps "{{" and "}}". These tests make sure that user text with Vue
// interpolation syntax stays text and never runs as JavaScript.
import { test, afterEach } from 'node:test'
import assert from 'node:assert/strict'
import { readFile } from 'node:fs/promises'
import * as vue from 'vue'
import { serverTemplateDelimiters } from '../../resources/js/server-template.js'

const node = (tag, text = '') => ({ tag, text, children: [] })

const { createApp } = vue.createRenderer({
  createElement: tag => node(tag),
  createText: text => node('#text', text),
  createComment: text => node('#comment', text),
  setText: (target, text) => { target.text = text },
  setElementText: (target, text) => { target.text = text },
  insert: (child, parent) => { parent.children.push(child); child.parent = parent },
  remove: () => {},
  parentNode: target => target.parent ?? null,
  nextSibling: () => null,
  patchProp: () => {},
})

function textOf(target) {
  return target.text + target.children.map(textOf).join('')
}

function mountServerHtml(html, compilerOptions) {
  const root = node('root')
  createApp({ render: vue.compile(html, compilerOptions) }).mount(root)
  return textOf(root)
}

// The browser gives Vue the template through innerHTML, which writes U+00A0 as "&nbsp;".
const payloads = [
  '{{ $emit.constructor(`globalThis.PWNED = 1`)() }}',
  '&nbsp;{{ $emit.constructor(`globalThis.PWNED = 1`)() }}&nbsp;',
]

afterEach(() => {
  delete globalThis.PWNED
})

test('the default delimiters run user text as code (control)', () => {
  mountServerHtml(`<div><h1>${payloads[0]}</h1></div>`, {})
  assert.equal(globalThis.PWNED, 1)
})

for (const payload of payloads) {
  test(`user text stays text with the app delimiters: ${payload}`, () => {
    const text = mountServerHtml(`<div><h1>${payload}</h1></div>`, { delimiters: serverTemplateDelimiters })

    assert.equal(globalThis.PWNED, undefined)
    assert.ok(text.includes('{{ $emit.constructor('))
  })
}

test('the app sets the delimiters before it mounts #app', async () => {
  const source = await readFile(new URL('../../resources/js/app.js', import.meta.url), 'utf8')
  const configured = source.indexOf('app.config.compilerOptions.delimiters = serverTemplateDelimiters')
  const mounted = source.indexOf("app.mount('#app')")

  assert.notEqual(configured, -1)
  assert.notEqual(mounted, -1)
  assert.ok(configured < mounted)
})
