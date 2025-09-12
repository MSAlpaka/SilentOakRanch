import { describe, it, expect, vi, afterEach } from 'vitest'
import { render, screen, fireEvent, cleanup } from '@testing-library/react'
import { Provider } from 'react-redux'
import { createElement } from 'react'
import AppointmentForm from '../../modules/appointments/AppointmentForm'

vi.mock('react-i18next', () => ({
  useTranslation: () => ({ t: (s: string) => s }),
}))

vi.mock('react-router-dom', () => ({
  useNavigate: () => vi.fn(),
}))

describe('AppointmentForm', () => {
  afterEach(() => {
    cleanup()
    vi.clearAllMocks()
  })

  it('requires all fields before submission', () => {
    const state = {
      appointments: {
        providers: [],
        serviceTypes: [],
        appointments: [],
      },
    }
    const dispatch = vi.fn()
    const store = { getState: () => state, dispatch, subscribe: vi.fn() }

    render(createElement(Provider, { store }, createElement(AppointmentForm)))

    const initialCalls = dispatch.mock.calls.length
    fireEvent.click(screen.getByText('appointments.save'))
    expect(dispatch.mock.calls.length).toBe(initialCalls)
  })

  it('shows only available slots to prevent collisions', () => {
    const available = {
      id: 1,
      start: '2024-01-01T10:00:00Z',
      status: 'available',
      provider: { id: 1, name: 'Vet' },
      serviceType: { id: 1, name: 'Checkup' },
    }
    const booked = {
      id: 2,
      start: '2024-01-01T11:00:00Z',
      status: 'booked',
      provider: { id: 1, name: 'Vet' },
      serviceType: { id: 1, name: 'Checkup' },
    }
    const state = {
      appointments: {
        providers: [{ id: 1, name: 'Vet' }],
        serviceTypes: [{ id: 1, name: 'Checkup' }],
        appointments: [available, booked],
      },
    }
    const store = { getState: () => state, dispatch: vi.fn(), subscribe: vi.fn() }

    render(createElement(Provider, { store }, createElement(AppointmentForm)))

    const slotSelect = screen.getAllByRole('combobox')[2] as HTMLSelectElement
    const options = Array.from(slotSelect.querySelectorAll('option'))
    expect(options.length).toBe(2)
    expect(options[1].value).toBe(available.start)
  })
})

