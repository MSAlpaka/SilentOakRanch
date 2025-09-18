import { describe, it, expect, vi, afterEach } from 'vitest'
import { render, screen, fireEvent, cleanup } from '@testing-library/react'
import { Provider } from 'react-redux'
import { createElement } from 'react'
import InvoiceDetail from '../InvoiceDetail'

vi.mock('react-i18next', () => ({
  useTranslation: () => ({ t: (s: string) => s }),
}))

vi.mock('react-router-dom', () => ({
  useParams: () => ({ id: '1' }),
}))

describe('InvoiceDetail', () => {
  afterEach(() => {
    cleanup()
  })

  it('opens invoice download link', () => {
    const invoice = {
      id: 1,
      createdAt: '2024-01-01T00:00:00Z',
      amount: '100',
      status: 'paid',
      downloadUrl: '/api/invoices/1',
    }

    const store = {
      getState: () => ({ invoices: { current: invoice } }),
      dispatch: vi.fn(),
      subscribe: vi.fn(),
    }

    const open = (window.open = vi.fn())

    render(
      createElement(Provider, { store }, createElement(InvoiceDetail))
    )

    fireEvent.click(screen.getByText('invoices.download'))
    expect(open).toHaveBeenCalledWith('/api/invoices/1', '_blank')
  })
})

