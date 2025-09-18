import { describe, it, expect, vi, beforeEach } from 'vitest'
import reducer, { fetchById } from './invoiceSlice'

const getMock = vi.fn()

vi.mock('../../axios', () => ({
  default: {
    get: (...args: unknown[]) => getMock(...args),
  },
}))

describe('invoiceSlice', () => {
  beforeEach(() => {
    getMock.mockReset()
  })

  it('fetchById loads metadata endpoint and sets current invoice', async () => {
    const invoice = {
      id: 5,
      createdAt: '2024-05-01T12:00:00Z',
      amount: '100.00',
      status: 'paid',
      downloadUrl: '/api/invoices/5',
    }

    getMock.mockResolvedValue({ data: invoice })

    const dispatch = vi.fn()
    const result = await fetchById(5)(dispatch, () => ({}), undefined)

    expect(getMock).toHaveBeenCalledWith('/invoices/5/meta')
    expect(result.payload).toEqual(invoice)

    const state = reducer(undefined, fetchById.fulfilled(invoice, '', 5))
    expect(state.current).toEqual(invoice)
  })
})
