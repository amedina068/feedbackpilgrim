// The project selector lists the projects of the user and a "New project"
// item. The item opens the onboarding form for a second project.
import { test } from 'node:test'
import assert from 'node:assert/strict'
import { readFile } from 'node:fs/promises'
import * as vue from 'vue'
import { parse, compileTemplate } from 'vue/compiler-sfc'
import { renderToString } from 'vue/server-renderer'

async function renderSelector(props) {
  const source = await readFile(new URL('../../resources/js/components/ClientSelector.vue', import.meta.url), 'utf8')
  const { descriptor } = parse(source)
  const { code } = compileTemplate({
    source: descriptor.template.content,
    id: 'client-selector',
    compilerOptions: { mode: 'function' },
  })

  const render = new Function('Vue', code)(vue)
  const component = {
    props: ['clients', 'client'],
    setup: () => ({ csrfToken: 'token', clientSelector: null, selectOption: () => {} }),
    render,
  }

  return renderToString(vue.createSSRApp(component, props))
}

test('the selector lists each project and a New project item', async () => {
  const html = await renderSelector({
    clients: [{ id: 1, name: 'Acme' }, { id: 2, name: 'Globex' }],
    client: { id: 2, name: 'Globex' },
  })

  assert.match(html, /<option[^>]*value="1"[^>]*>Acme<\/option>/)
  assert.match(html, /<option selected value="2">Globex<\/option>/)
  assert.match(html, /<option[^>]*data-href="\/onboarding\/project"[^>]*>New project<\/option>/)
})
