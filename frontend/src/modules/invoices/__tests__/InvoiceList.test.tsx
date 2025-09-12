import { describe, it, expect, vi, afterEach } from 'vitest'
import { render, screen, cleanup } from '@testing-library/react'
import { Provider } from 'react-redux'
import { createElement } from 'react'
import InvoiceList from '../InvoiceList'

vi.mock('react-i18next', () => ({
  useTranslation: () => ({ t: (s: string) => s }),
}))

describe('InvoiceList', () => {
  afterEach(() => {
    cleanup()
  })

  it('renders invoices with download links', () => {
    const invoices = [
      {
        id: 1,
        createdAt: '2024-01-01T00:00:00Z',
        amount: '100',
        status: 'paid',
        downloadUrl: '/one.pdf',
      },
      {
        id: 2,
        createdAt: '2024-02-02T00:00:00Z',
        amount: '200',
        status: 'pending',
        downloadUrl: '/two.pdf',
      },
    ]

    const store = {
      getState: () => ({ invoices: { items: invoices } }),
      dispatch: vi.fn(),
      subscribe: vi.fn(),
    }

    render(
      createElement(Provider, { store }, createElement(InvoiceList))
    )

    expect(screen.getByText('100')).toBeTruthy()
    expect(screen.getByText('200')).toBeTruthy()

    const links = screen.getAllByRole('link', { name: 'invoices.download' })
    expect(links.length).toBe(2)
    expect(links[0].getAttribute('href')).toBe('/one.pdf')
    expect(links[1].getAttribute('href')).toBe('/two.pdf')
  })
})

