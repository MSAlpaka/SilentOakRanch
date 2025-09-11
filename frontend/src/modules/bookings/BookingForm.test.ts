import { describe, it, expect, vi, beforeEach, afterEach } from 'vitest'
import { render, screen, fireEvent, waitFor, cleanup } from '@testing-library/react'
import { createElement } from 'react'
import BookingForm from './BookingForm'

const navigateMock = vi.hoisted(() => vi.fn())
const postMock = vi.hoisted(() => vi.fn())

vi.mock('../../axios', () => ({
  default: { post: postMock },
}))

vi.mock('react-router-dom', () => ({
  useNavigate: () => navigateMock,
}))

vi.mock('react-i18next', () => ({
  useTranslation: () => ({ t: (s: string) => s }),
}))

describe('BookingForm', () => {
  beforeEach(() => {
    vi.clearAllMocks()
    postMock.mockResolvedValue({})
  })

  afterEach(() => {
    cleanup()
  })

  it('renders required fields', () => {
    render(createElement(BookingForm))
    expect(screen.getAllByRole('combobox').length).toBe(2)
    const today = new Date().toISOString().split('T')[0]
    expect(screen.getAllByDisplayValue(today).length).toBe(1)
  })

  it('submits form data to API', async () => {
    render(createElement(BookingForm))

    const [horseSelect, packageSelect] = screen.getAllByRole('combobox')
    const today = new Date().toISOString().split('T')[0]
    const dateInput = screen.getAllByDisplayValue(today)[0]

    fireEvent.change(horseSelect, { target: { value: '1' } })
    fireEvent.change(packageSelect, { target: { value: '2' } })
    fireEvent.change(dateInput, { target: { value: '2024-01-01' } })

    fireEvent.click(screen.getByText('bookings.book'))

    await waitFor(() => {
      expect(postMock).toHaveBeenCalledWith('/bookings', {
        horseId: 1,
        packageId: 2,
        startDate: '2024-01-01',
      })
      expect(navigateMock).toHaveBeenCalledWith('/bookings')
    })
  })
})
