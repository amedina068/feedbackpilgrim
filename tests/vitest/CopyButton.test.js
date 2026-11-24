// The copy button says "Copied" only when the clipboard took the text. Then
// it goes back to its label. When the clipboard is not available, the button
// tells the user to select the text.
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import { flushPromises, mount } from '@vue/test-utils'
import CopyButton from '../../resources/js/components/CopyButton.vue'

describe('CopyButton', () => {
  beforeEach(() => {
    vi.useFakeTimers()
  })

  afterEach(() => {
    vi.useRealTimers()
  })

  it('copies the text, says Copied, and shows the label again after 1.5 seconds', async () => {
    const writeText = vi.fn().mockResolvedValue(undefined)
    vi.stubGlobal('navigator', { clipboard: { writeText } })
    const wrapper = mount(CopyButton, { props: { text: 'token-123', label: 'Copy token' } })

    expect(wrapper.attributes('aria-label')).toBe('Copy token')

    await wrapper.trigger('click')
    await flushPromises()

    expect(writeText).toHaveBeenCalledWith('token-123')
    expect(wrapper.text()).toBe('Copied')
    expect(wrapper.attributes('aria-label')).toBeUndefined()

    await vi.advanceTimersByTimeAsync(1499)
    expect(wrapper.text()).toBe('Copied')

    await vi.advanceTimersByTimeAsync(1)
    expect(wrapper.text()).toBe('Copy token')
  })

  it('shows the failed label for 4 seconds when the clipboard does not exist', async () => {
    vi.stubGlobal('navigator', {})
    const wrapper = mount(CopyButton, { props: { text: 'token-123' } })

    await wrapper.trigger('click')
    await flushPromises()

    expect(wrapper.text()).toBe('Copy failed. Select the text.')

    await vi.advanceTimersByTimeAsync(3999)
    expect(wrapper.text()).toBe('Copy failed. Select the text.')

    await vi.advanceTimersByTimeAsync(1)
    expect(wrapper.text()).toBe('Copy')
  })
})
