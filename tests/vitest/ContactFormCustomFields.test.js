// The contact form adds custom fields as rows. Each row sends its value as
// custom_fields[<field name>], so a new name for the field changes the key
// that the form sends.
import { describe, expect, it } from 'vitest'
import { mount } from '@vue/test-utils'
import ContactFormCustomFields from '../../resources/js/components/ContactFormCustomFields.vue'

const valueInputs = (wrapper) => wrapper.findAll('input[name^="custom_fields["]')
const addButton = (wrapper) => wrapper.findAll('button').find((button) => button.text() === 'Add Custom Field')
const removeButtons = (wrapper) => wrapper.findAll('button').filter((button) => button.text() === 'Remove field')
const nameInputs = (wrapper) => wrapper.findAll('input[aria-label="Field name"]')

const fillRow = async (wrapper, index, name, value) => {
  await nameInputs(wrapper)[index].setValue(name)
  await valueInputs(wrapper)[index].setValue(value)
}

const mountInForm = () =>
  mount({
    components: { ContactFormCustomFields },
    template: '<form><ContactFormCustomFields /></form>',
  })

const sentFields = (wrapper) => [...new FormData(wrapper.find('form').element).entries()]

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

    await removeButtons(wrapper)[1].trigger('click')

    expect(valueInputs(wrapper).map((input) => input.attributes('name'))).toEqual([
      'custom_fields[custom_field_1]',
    ])
  })

  it('keeps the second row visible when the user removes the first row', async () => {
    const wrapper = mount(ContactFormCustomFields)
    await addButton(wrapper).trigger('click')
    await addButton(wrapper).trigger('click')
    await wrapper.findAll('input[aria-label="Field name"]')[1].setValue('plan')

    await removeButtons(wrapper)[0].trigger('click')

    expect(wrapper.findAll('input[aria-label="Field name"]').map((input) => input.element.value)).toEqual(['plan'])
    expect(valueInputs(wrapper).map((input) => input.attributes('name'))).toEqual([
      'custom_fields[plan]',
    ])
  })

  it('gives a new row a field name that no other row uses after a removal', async () => {
    const wrapper = mount(ContactFormCustomFields)
    await addButton(wrapper).trigger('click')
    await addButton(wrapper).trigger('click')

    await removeButtons(wrapper)[0].trigger('click')
    await addButton(wrapper).trigger('click')

    expect(valueInputs(wrapper).map((input) => input.attributes('name'))).toEqual([
      'custom_fields[custom_field_2]',
      'custom_fields[custom_field_3]',
    ])
  })

  it('keeps the name and the value of the second row when the user removes the first row', async () => {
    const wrapper = mountInForm()
    await addButton(wrapper).trigger('click')
    await addButton(wrapper).trigger('click')
    await fillRow(wrapper, 0, 'company', 'Acme')
    await fillRow(wrapper, 1, 'plan', 'pro')

    await removeButtons(wrapper)[0].trigger('click')

    expect(nameInputs(wrapper).map((input) => input.element.value)).toEqual(['plan'])
    expect(valueInputs(wrapper).map((input) => input.element.value)).toEqual(['pro'])
    expect(valueInputs(wrapper)[0].attributes('aria-label')).toBe('Value of plan')
    expect(sentFields(wrapper)).toEqual([['custom_fields[plan]', 'pro']])
  })

  it('keeps the first and the third rows when the user removes the middle row', async () => {
    const wrapper = mountInForm()
    await addButton(wrapper).trigger('click')
    await addButton(wrapper).trigger('click')
    await addButton(wrapper).trigger('click')
    await fillRow(wrapper, 0, 'company', 'Acme')
    await fillRow(wrapper, 1, 'seats', '12')
    await fillRow(wrapper, 2, 'plan', 'pro')

    await removeButtons(wrapper)[1].trigger('click')

    expect(nameInputs(wrapper).map((input) => input.element.value)).toEqual(['company', 'plan'])
    expect(valueInputs(wrapper).map((input) => input.element.value)).toEqual(['Acme', 'pro'])
    expect(sentFields(wrapper)).toEqual([
      ['custom_fields[company]', 'Acme'],
      ['custom_fields[plan]', 'pro'],
    ])
  })
})
