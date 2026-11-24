// A flash message shows its text, then hides after the delay. The default
// delay is 2 seconds.
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import { mount } from '@vue/test-utils'
import AutoHideFlash from '../../resources/js/components/AutoHideFlash.vue'

const mountFlash = (props = {}) =>
  mount(AutoHideFlash, {
    props,
    slots: { default: 'Settings saved.' },
    global: { stubs: { transition: false } },
  })

describe('AutoHideFlash', () => {
  beforeEach(() => {
    vi.useFakeTimers()
  })

  afterEach(() => {
    vi.useRealTimers()
  })

  it('shows the message as a status and hides it after 2 seconds', async () => {
    const wrapper = mountFlash()
    const message = wrapper.find('[role="status"]')

    expect(message.text()).toBe('Settings saved.')
    expect(message.attributes('aria-live')).toBe('polite')

    await vi.advanceTimersByTimeAsync(1999)
    expect(wrapper.find('[role="status"]').exists()).toBe(true)

    await vi.advanceTimersByTimeAsync(1)
    expect(wrapper.find('[role="status"]').exists()).toBe(false)
  })

  it('obeys a custom delay', async () => {
    const wrapper = mountFlash({ delay: 5000 })

    await vi.advanceTimersByTimeAsync(4999)
    expect(wrapper.find('[role="status"]').exists()).toBe(true)

    await vi.advanceTimersByTimeAsync(1)
    expect(wrapper.find('[role="status"]').exists()).toBe(false)
  })
})
