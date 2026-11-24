// The contact form adds custom fields as rows. Each row sends its value as
// custom_fields[<field name>], so a new name for the field changes the key
// that the form sends.
import { describe, expect, it } from 'vitest'
import { mount } from '@vue/test-utils'
import ContactFormCustomFields from '../../resources/js/components/ContactFormCustomFields.vue'

const valueInputs = (wrapper) => wrapper.findAll('input[name^="custom_fields["]')
const addButton = (wrapper) => wrapper.findAll('button').find((button) => button.text() === 'Add Custom Field')

describe('ContactFormCustomFields', () => {
  it('adds a row with a numbered field name for each click', async () => {
    const wrapper = mount(ContactFormCustomFields)

    expect(valueInputs(wrapper)).toHaveLength(0)

    await addButton(wrapper).trigger('click')
    await addButton(wrapper).trigger('click')

    expect(valueInputs(wrapper).map((input) => input.attributes('name'))).toEqual([
      'custom_fields[custom_field_1]',
      'custom_fields[custom_field_2]',
    ])
  })

  it('sends the value under the field name that the user types', async () => {
    const wrapper = mount(ContactFormCustomFields)
    await addButton(wrapper).trigger('click')

    await wrapper.find('input[aria-label="Field name"]').setValue('plan')

    expect(valueInputs(wrapper)[0].attributes('name')).toBe('custom_fields[plan]')
    expect(valueInputs(wrapper)[0].attributes('aria-label')).toBe('Value of plan')
  })

  it('removes a row when the user clicks Remove field', async () => {
    const wrapper = mount(ContactFormCustomFields)
    await addButton(wrapper).trigger('click')
    await addButton(wrapper).trigger('click')

    const removeButtons = wrapper.findAll('button').filter((button) => button.text() === 'Remove field')
    await removeButtons[1].trigger('click')

    expect(valueInputs(wrapper).map((input) => input.attributes('name'))).toEqual([
      'custom_fields[custom_field_1]',
    ])
  })
})
