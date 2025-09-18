import { describe, it, expect, vi, beforeEach, afterEach } from 'vitest'
import { render, screen, fireEvent, cleanup, waitFor } from '@testing-library/react'
import { Provider } from 'react-redux'
import { createElement } from 'react'
import AppointmentForm from '../../modules/appointments/AppointmentForm'

const createAppointmentMock = vi.hoisted(() => vi.fn())
const getProvidersMock = vi.hoisted(() => vi.fn())
const getServiceTypesMock = vi.hoisted(() => vi.fn())
const getAppointmentsMock = vi.hoisted(() => vi.fn())
const confirmAppointmentMock = vi.hoisted(() => vi.fn())
const completeAppointmentMock = vi.hoisted(() => vi.fn())
const cancelAppointmentMock = vi.hoisted(() => vi.fn())
const axiosGetMock = vi.hoisted(() => vi.fn())

vi.mock('../../api/appointments', () => ({
  createAppointment: createAppointmentMock,
  getProviders: getProvidersMock,
  getServiceTypes: getServiceTypesMock,
  getAppointments: getAppointmentsMock,
  confirmAppointment: confirmAppointmentMock,
  completeAppointment: completeAppointmentMock,
  cancelAppointment: cancelAppointmentMock,
}))

vi.mock('../../axios', () => ({
  default: { get: axiosGetMock },
}))

vi.mock('react-i18next', () => ({
  useTranslation: () => ({ t: (s: string) => s }),
}))

vi.mock('react-router-dom', () => ({
  useNavigate: () => vi.fn(),
}))

describe('AppointmentForm', () => {
  beforeEach(() => {
    axiosGetMock.mockResolvedValue({ data: [] })
    getProvidersMock.mockResolvedValue([])
    getServiceTypesMock.mockResolvedValue([])
    getAppointmentsMock.mockResolvedValue([])
    createAppointmentMock.mockResolvedValue({
      id: 1,
      horse: { id: 1, name: 'Star' },
      owner: { id: 1, name: 'Owner' },
      provider: { id: 1, name: 'Vet' },
      serviceType: { id: 1, name: 'Checkup' },
      start: '2024-01-01T10:00:00Z',
      end: '2024-01-01T11:00:00Z',
      status: 'requested',
    })
    confirmAppointmentMock.mockResolvedValue({})
    completeAppointmentMock.mockResolvedValue({})
    cancelAppointmentMock.mockResolvedValue({})
  })

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

    const serviceTypeSelect = screen.getByLabelText('appointments.service_type') as HTMLSelectElement
    fireEvent.change(serviceTypeSelect, { target: { value: '1' } })

    const startInput = screen.getByLabelText('appointments.slot') as HTMLInputElement
    fireEvent.change(startInput, { target: { value: '2024-01-01T10:00' } })

    const endInput = screen.getByLabelText('appointments.end_time') as HTMLInputElement
    expect(endInput.value).toBe('2024-01-01T11:00')
  })

  it('submits the selected horse with the appointment payload', async () => {
    axiosGetMock.mockResolvedValueOnce({ data: [{ id: 1, name: 'Star' }] })

    const state = {
      appointments: {
        providers: [{ id: 1, name: 'Vet' }],
        serviceTypes: [{ id: 1, name: 'Checkup', defaultDurationMinutes: 60 }],
        appointments: [],
      },
    }

    const dispatch = vi.fn((action: any) => {
      if (typeof action === 'function') {
        return action(dispatch, () => state, undefined)
      }
      return action
    })

    const store = { getState: () => state, dispatch, subscribe: vi.fn() }

    render(createElement(Provider, { store }, createElement(AppointmentForm)))

    const horseSelect = await screen.findByLabelText('appointments.horse')
    fireEvent.change(horseSelect, { target: { value: '1' } })

    const providerSelect = screen.getByLabelText('appointments.provider') as HTMLSelectElement
    fireEvent.change(providerSelect, { target: { value: '1' } })

    const serviceTypeSelect = screen.getByLabelText('appointments.service_type') as HTMLSelectElement
    fireEvent.change(serviceTypeSelect, { target: { value: '1' } })

    const startInput = screen.getByLabelText('appointments.slot') as HTMLInputElement
    fireEvent.change(startInput, { target: { value: '2024-01-01T10:00' } })

    const endInput = screen.getByLabelText('appointments.end_time') as HTMLInputElement
    expect(endInput.value).toBe('2024-01-01T11:00')

    fireEvent.click(screen.getByText('appointments.save'))

    await waitFor(() => {
      expect(createAppointmentMock).toHaveBeenCalledWith(
        expect.objectContaining({
          horseId: 1,
          providerId: 1,
          serviceTypeId: 1,
        })
      )
    })
  })
})

