/**
 * Copy the stages and their post lists, so that the board can go back to
 * this state when the server refuses a move.
 */
export function snapshotStages(stages) {
  return stages.map((stage) => ({ ...stage, posts: [...stage.posts] }))
}

/**
 * Make the message that the board shows when a move fails.
 */
export function moveErrorMessage(error) {
  const status = error?.response?.status

  if (status === 419) {
    return 'Your session expired. Reload the page and move the post again.'
  }

  if (status === 422 && error.response.data?.message) {
    return `Could not move the post: ${error.response.data.message}`
  }

  return 'Could not move the post. Check your connection and try again.'
}
