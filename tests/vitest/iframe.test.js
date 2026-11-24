// The release notes and testimonials modals run in an iframe. useIframe opens
// the modal on an "open" message, and asks the parent page to hide the iframe
// after the modal closes.
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import { nextTick } from 'vue'
import { useIframe } from '../../resources/js/composables/iframe.js'

const sendMessage = (data) => window.dispatchEvent(new MessageEvent('message', { data }))

describe('useIframe', () => {
  let postMessage
  let addEventListener

  beforeEach(() => {
    vi.useFakeTimers()
    postMessage = vi.spyOn(window.parent, 'postMessage').mockImplementation(() => {})
    addEventListener = vi.spyOn(window, 'addEventListener')
  })

  afterEach(() => {
    // Remove the listeners of this test, so that the next test gets only its own.
    for (const [type, listener] of addEventListener.mock.calls) {
      window.removeEventListener(type, listener)
    }
    vi.useRealTimers()
  })

  it('tells the parent page that the iframe is registered', () => {
    const { registerIframe } = useIframe()

    registerIframe({ iframe: 'pilgrim-release-notes' })

    expect(postMessage).toHaveBeenCalledWith(
      { action: 'registered', data: { iframe: 'pilgrim-release-notes' } },
      '*',
    )
  })

  it('opens the modal 350 ms after an open message', async () => {
    const { open, listen } = useIframe()
    listen()

    sendMessage({ action: 'open' })
    await nextTick()
    await vi.advanceTimersByTimeAsync(349)
    expect(open.value).toBe(false)

    await vi.advanceTimersByTimeAsync(1)
    expect(open.value).toBe(true)
  })

  it('closes the modal and asks the parent page to hide the iframe 500 ms later', async () => {
    const { open, listen, registerIframe } = useIframe()
    registerIframe({ iframe: 'pilgrim-testimonials' })
    listen()
    open.value = true
    await nextTick()
    postMessage.mockClear()

    sendMessage({ action: 'close' })
    await nextTick()
    await nextTick()
    expect(open.value).toBe(false)

    await vi.advanceTimersByTimeAsync(499)
    expect(postMessage).not.toHaveBeenCalled()

    await vi.advanceTimersByTimeAsync(1)
    expect(postMessage).toHaveBeenCalledWith({ action: 'hide-iframe', iframe: 'pilgrim-testimonials' }, '*')
  })
})
