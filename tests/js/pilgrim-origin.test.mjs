// Builds resources/js/pilgrim.js with the real Vite config and runs the
// output in a small fake browser. The widget must send its init request to
// the host that served the script, for every host, with one build.
import { test, before, after } from 'node:test'
import assert from 'node:assert/strict'
import { mkdtemp, readFile, rm } from 'node:fs/promises'
import { tmpdir } from 'node:os'
import { join } from 'node:path'
import vm from 'node:vm'
import { build } from 'vite'

let outDir
let bundle

before(async () => {
  outDir = await mkdtemp(join(tmpdir(), 'pilgrim-build-'))
  await build({
    configFile: 'vite.config.external-script.js',
    logLevel: 'silent',
    build: { outDir, manifest: false },
  })
  bundle = await readFile(join(outDir, 'pilgrim.js'), 'utf8')
})

after(async () => {
  await rm(outDir, { recursive: true, force: true })
})

function fakeScript(src) {
  return {
    src,
    getAttribute: name => (name === 'data-client' ? 'pk_test' : null),
  }
}

function runWidget({ pageUrl, currentScript, taggedScripts = [] }) {
  const requests = []
  const document = {
    currentScript,
    querySelector: selector => {
      const match = selector.match(/^script\[src([$*])="([^"]+)"\]\[data-client\]$/)
      if (!match) return null
      const [, operator, value] = match
      const matches = src => (operator === '$' ? src.endsWith(value) : src.includes(value))
      return taggedScripts.find(script => matches(script.src)) ?? null
    },
    querySelectorAll: () => [],
    getElementById: () => null,
    body: { appendChild: () => {} },
  }
  const window = { location: { href: pageUrl }, addEventListener: () => {} }
  const context = {
    window,
    document,
    URL,
    Headers,
    setTimeout,
    fetch: (url, options) => {
      requests.push({ url, options })
      return new Promise(() => {})
    },
  }
  vm.runInNewContext(bundle, context)
  window.pilgrim({ contact: { email: 'jane@example.com' }, userHash: 'hash-value' })
  return requests
}

for (const host of ['https://feedbackpilgrim.com', 'https://app.feedbackpilgrim.com']) {
  test(`sends init to the host that served the script: ${host}`, () => {
    const requests = runWidget({
      pageUrl: 'https://shop.example.org/collection',
      currentScript: fakeScript(`${host}/pilgrim.js`),
    })

    assert.equal(requests.length, 1)
    assert.equal(requests[0].url, `${host}/api/v1/init`)
    assert.equal(requests[0].options.headers.get('Authorization'), 'Bearer pk_test')
    assert.equal(JSON.parse(requests[0].options.body).user_hash, 'hash-value')
  })
}

test('finds its tag by file name when document.currentScript is null', () => {
  const requests = runWidget({
    pageUrl: 'https://blog.example.org/',
    currentScript: null,
    taggedScripts: [fakeScript('https://app.feedbackpilgrim.com/pilgrim.js')],
  })

  assert.equal(requests[0].url, 'https://app.feedbackpilgrim.com/api/v1/init')
  assert.equal(requests[0].options.headers.get('Authorization'), 'Bearer pk_test')
})
