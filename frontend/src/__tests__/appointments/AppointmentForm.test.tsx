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

  it('prefills the end time based on the selected service duration', () => {
    const state = {
      appointments: {
        providers: [{ id: 1, name: 'Vet' }],
        serviceTypes: [
          { id: 1, name: 'Checkup', defaultDurationMinutes: 60 },
          { id: 2, name: 'Short', defaultDurationMinutes: 15 },
        ],
        appointments: [],
      },
    }
    const store = { getState: () => state, dispatch: vi.fn(), subscribe: vi.fn() }

    render(createElement(Provider, { store }, createElement(AppointmentForm)))

    const [, serviceTypeSelect] = screen.getAllByRole('combobox') as HTMLSelectElement[]
    fireEvent.change(serviceTypeSelect, { target: { value: '1' } })

    const startInput = screen.getByLabelText('appointments.slot') as HTMLInputElement
    fireEvent.change(startInput, { target: { value: '2024-01-01T10:00' } })

    const endInput = screen.getByLabelText('appointments.end_time') as HTMLInputElement
    expect(endInput.value).toBe('2024-01-01T11:00')
  })
})

