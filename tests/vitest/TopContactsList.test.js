// The dashboard ranks the contacts by logins, by published posts, or by all
// activity. Each type shows its own metric, label and empty state.
import { describe, expect, it } from 'vitest'
import { mount } from '@vue/test-utils'
import TopContactsList from '../../resources/js/components/TopContactsList.vue'

const contacts = [
  { id: 7, name: 'Ada', email: 'ada@example.com', avatar: '/a.png', top_customer: true, login_count: 1, published_posts_count: 3, total_activity: 9 },
  { id: 8, name: null, email: 'nameless@example.com', avatar: '/b.png', top_customer: false, login_count: 4, published_posts_count: 1, total_activity: 2 },
]

const rows = (wrapper) => wrapper.findAll('li')

describe('TopContactsList', () => {
  it('ranks the contacts and links each one to its contact page', () => {
    const wrapper = mount(TopContactsList, { props: { contacts, title: 'Top logins', type: 'logins' } })

    expect(wrapper.find('h3').text()).toBe('Top logins')
    expect(rows(wrapper)).toHaveLength(2)
    expect(rows(wrapper)[0].find('a').attributes('href')).toBe('/contacts/7')
    expect(rows(wrapper)[0].text()).toContain('Top Customer')
    expect(rows(wrapper)[1].find('a').text()).toBe('N/A')
    expect(rows(wrapper)[1].text()).not.toContain('Top Customer')
  })

  it.each([
    ['logins', ['1', 'login'], ['4', 'logins']],
    ['posts', ['3', 'published posts'], ['1', 'published post']],
    ['activity', ['9', 'total actions'], ['2', 'total actions']],
  ])('shows the %s metric with a singular or plural label', (type, first, second) => {
    const wrapper = mount(TopContactsList, { props: { contacts, title: 'Top', type } })
    const metric = (row) => row.findAll('.shrink-0.text-right p').map((p) => p.text())

    expect(metric(rows(wrapper)[0])).toEqual(first)
    expect(metric(rows(wrapper)[1])).toEqual(second)
  })

  it.each([
    ['logins', 'No contacts have logged in in the past 7 days.'],
    ['posts', 'No contacts have published posts in the past 7 days.'],
    ['activity', 'No contacts have any activity in the past 7 days.'],
  ])('shows the %s empty state when no contact has activity', (type, text) => {
    const wrapper = mount(TopContactsList, { props: { contacts: [], title: 'Top', type } })

    expect(wrapper.find('ol').exists()).toBe(false)
    expect(wrapper.text()).toContain('No activity yet')
    expect(wrapper.text()).toContain(text)
  })
})
