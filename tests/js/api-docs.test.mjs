// The Postman files must be the same on each run, so that CI can compare
// them with the committed files.
import { test } from 'node:test'
import assert from 'node:assert/strict'
import { postmanCollection, postmanEnvironment } from '../../scripts/api-docs.mjs'

function converted() {
  return {
    info: { _postman_id: 'random-id', name: 'Feedback Pilgrim API', schema: 'https://schema.getpostman.com/json/collection/v2.1.0/collection.json' },
    auth: { type: 'bearer', bearer: [{ key: 'token', value: '{{bearerToken}}', type: 'any' }] },
    variable: [{ key: 'baseUrl', value: 'https://app.example.com' }],
    item: [
      {
        name: 'Posts',
        item: [{
          id: 'random-item-id',
          name: 'List posts',
          request: { method: 'GET', url: { host: ['{{baseUrl}}'], path: ['api', 'public', 'v1', 'posts'] }, auth: null },
          response: [{ id: 'random-response-id', name: 'One page of posts.', body: '{"id": 1}' }],
        }],
      },
      {
        name: 'Widget',
        item: [{
          id: 'random-item-id',
          name: 'Start the widget',
          request: { method: 'POST', url: { host: ['{{baseUrl}}'], path: ['api', 'v1', 'init'] }, auth: null },
          response: [],
        }],
      },
    ],
  }
}

const bearer = (variable) => ({ type: 'bearer', bearer: [{ key: 'token', value: `{{${variable}}}`, type: 'string' }] })

test('the collection sends the API token from the token variable', () => {
  assert.deepEqual(postmanCollection(converted()).auth, bearer('token'))
})

test('the widget init request sends the publishable key', () => {
  const [, widget] = postmanCollection(converted()).item

  assert.deepEqual(widget.item[0].request.auth, bearer('publishableKey'))
})

test('the other requests inherit the auth of the collection', () => {
  const [posts] = postmanCollection(converted()).item

  assert.equal('auth' in posts.item[0].request, false)
})

test('the collection has no random ids', () => {
  const json = JSON.stringify(postmanCollection(converted()))

  assert.doesNotMatch(json, /random/)
  assert.match(json, /"body":"{\\"id\\": 1}"/)
})

test('the environment has a placeholder for each variable', () => {
  assert.deepEqual(
    Object.fromEntries(postmanEnvironment().values.map(({ key, value }) => [key, value])),
    { baseUrl: 'https://app.example.com', token: 'YOUR_API_TOKEN', publishableKey: 'pk_YOUR_PUBLISHABLE_KEY' },
  )
})
