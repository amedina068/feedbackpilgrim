// The copy buttons say "Copied" only when the clipboard took the text.
// On a page served over plain http, navigator.clipboard does not exist.
import { test } from 'node:test'
import assert from 'node:assert/strict'
import { copyText } from '../../resources/js/composables/clipboard.js'

test('copyText returns true when the clipboard takes the text', async () => {
  let written = null
  const clipboard = { writeText: async (text) => { written = text } }

  assert.equal(await copyText('token-123', clipboard), true)
  assert.equal(written, 'token-123')
})

test('copyText returns false when the clipboard does not exist', async () => {
  assert.equal(await copyText('token-123', undefined), false)
})

test('copyText returns false when the clipboard refuses the text', async () => {
  const clipboard = { writeText: async () => { throw new Error('NotAllowedError') } }

  assert.equal(await copyText('token-123', clipboard), false)
})
