import { describe, it, expect, vi, beforeEach, afterEach } from 'vitest'
import { render, screen, fireEvent, waitFor, cleanup } from '@testing-library/react'
import { createElement } from 'react'
import ScaleBookingForm from './ScaleBookingForm'

const navigateMock = vi.hoisted(() => vi.fn())
const getMock = vi.hoisted(() => vi.fn())
const postMock = vi.hoisted(() => vi.fn())

vi.mock('../../axios', () => ({
  default: { get: getMock, post: postMock },
}))

vi.mock('react-router-dom', () => ({
  useNavigate: () => navigateMock,
}))

describe('ScaleBookingForm', () => {
  beforeEach(() => {
    vi.clearAllMocks()
    const horses = [{ id: 1, name: 'Star' }]
    const slots = ['2024-01-01T10:00:00Z']
    getMock.mockResolvedValueOnce({ data: horses })
    getMock.mockResolvedValueOnce({ data: slots })
    postMock.mockRejectedValue(new Error('Invalid slot'))
  })

  afterEach(() => {
    cleanup()
  })

  it('shows error when submitting with invalid slot', async () => {
    render(createElement(ScaleBookingForm))

    await screen.findByText('Star')
    const [horseSelect] = screen.getAllByRole('combobox')
    fireEvent.change(horseSelect, { target: { value: '1' } })

    fireEvent.click(screen.getByText('Book'))

    await waitFor(() => {
      expect(postMock).toHaveBeenCalledWith('/scale/bookings', {
        horseId: 1,
        slot: '',
        type: 'single',
      })
    })

    expect(await screen.findByText('Failed to create booking')).toBeTruthy()
    expect(navigateMock).not.toHaveBeenCalled()
  })
})

