import { describe, it, expect, vi, beforeEach, afterEach } from 'vitest'
import { render, screen, waitFor, cleanup } from '@testing-library/react'
import { createElement } from 'react'
import ScaleBookingList from './ScaleBookingList'

const getMock = vi.hoisted(() => vi.fn())

vi.mock('../../axios', () => ({
  default: { get: getMock },
}))

describe('ScaleBookingList', () => {
  beforeEach(() => {
    vi.clearAllMocks()
  })

  afterEach(() => {
    cleanup()
  })

  it('renders bookings with status, qr code, and weight', async () => {
    const bookings = [
      {
        id: '1',
        horse: { name: 'Star' },
        slot: '2024-01-01T10:00:00Z',
        status: 'pending',
        qrImage: 'data:image/png;base64,token123',
        weight: null,
      },
      {
        id: '2',
        horse: 'Ghost',
        slot: '2024-01-02T12:00:00Z',
        status: 'completed',
        qrImage: 'data:image/png;base64,token456',
        weight: 500,
      },
    ]
    getMock.mockResolvedValue({ data: bookings })

    render(createElement(ScaleBookingList))

    await waitFor(() => {
      expect(getMock).toHaveBeenCalledWith('/scale/bookings/my')
    })

    expect(await screen.findByText('pending')).toBeTruthy()
    expect(await screen.findByText('completed')).toBeTruthy()

    const images = await screen.findAllByAltText('QR')
    expect(images).toHaveLength(2)
    expect(images[0].getAttribute('src')).toBe('data:image/png;base64,token123')
    expect(images[1].getAttribute('src')).toBe('data:image/png;base64,token456')

    expect(await screen.findByText('-')).toBeTruthy()
    expect(await screen.findByText('500')).toBeTruthy()
  })
})

