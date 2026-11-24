/**
 * Copy text to the clipboard. Return true only when the clipboard took the
 * text. The Clipboard API exists only on https pages and on localhost.
 */
export async function copyText(text, clipboard = globalThis.navigator?.clipboard) {
  if (!clipboard?.writeText) {
    return false
  }

  try {
    await clipboard.writeText(text)

    return true
  } catch {
    return false
  }
}
