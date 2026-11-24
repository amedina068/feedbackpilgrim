// The roadmap board moves a post before the server answers. When the
// server refuses the move, the board puts the posts back and shows why.
import { test } from 'node:test'
import assert from 'node:assert/strict'
import { snapshotStages, moveErrorMessage } from '../../resources/js/composables/stageMove.js'

test('a snapshot keeps the order of the posts after the board changes', () => {
  const stages = [
    { id: 1, name: 'Open', posts: [{ id: 10 }, { id: 11 }] },
    { id: 2, name: 'Planned', posts: [] },
  ]

  const before = snapshotStages(stages)
  const moved = stages[0].posts.splice(0, 1)[0]
  stages[1].posts.push(moved)

  assert.deepEqual(before.map((stage) => stage.posts.map((post) => post.id)), [[10, 11], []])
})

test('an expired session asks the user to reload', () => {
  assert.equal(
    moveErrorMessage({ response: { status: 419 } }),
    'Your session expired. Reload the page and move the post again.',
  )
})

test('a validation error shows the message of the server', () => {
  assert.equal(
    moveErrorMessage({ response: { status: 422, data: { message: 'The selected stage id is invalid.' } } }),
    'Could not move the post: The selected stage id is invalid.',
  )
})

test('a network error has a generic message', () => {
  assert.equal(
    moveErrorMessage(new Error('Network Error')),
    'Could not move the post. Check your connection and try again.',
  )
})
