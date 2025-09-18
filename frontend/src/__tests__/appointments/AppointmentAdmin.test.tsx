import { describe, it, expect, vi, beforeEach, afterEach } from 'vitest'
import { render, screen, fireEvent, waitFor, cleanup } from '@testing-library/react'
import { Provider } from 'react-redux'
import { createElement } from 'react'
import AppointmentAdmin from '../../modules/appointments/AppointmentAdmin'

const confirmMock = vi.hoisted(() => vi.fn())
const completeMock = vi.hoisted(() => vi.fn())
const cancelMock = vi.hoisted(() => vi.fn())
const getProvidersMock = vi.hoisted(() => vi.fn())
const getServiceTypesMock = vi.hoisted(() => vi.fn())
const getAppointmentsMock = vi.hoisted(() => vi.fn())

vi.mock('../../api/appointments', () => ({
  confirmAppointment: confirmMock,
  completeAppointment: completeMock,
  cancelAppointment: cancelMock,
  getProviders: getProvidersMock,
  getServiceTypes: getServiceTypesMock,
  getAppointments: getAppointmentsMock,
  createAppointment: vi.fn(),
}))

vi.mock('react-i18next', () => ({
  useTranslation: () => ({ t: (s: string) => s }),
}))

describe('AppointmentAdmin', () => {
  beforeEach(() => {
    confirmMock.mockResolvedValue({})
    completeMock.mockResolvedValue({})
    cancelMock.mockResolvedValue({})
    getProvidersMock.mockResolvedValue([])
    getServiceTypesMock.mockResolvedValue([])
    getAppointmentsMock.mockResolvedValue([])
  })

  afterEach(() => {
    cleanup()
    vi.clearAllMocks()
  })

  it('renders appointments and triggers status updates', async () => {
    const appointments = [
      {
        id: 1,
        horse: { id: 1, name: 'Star' },
        owner: { id: 1, name: 'Rider' },
        provider: { id: 1, name: 'Vet' },
        serviceType: { id: 1, name: 'Checkup' },
        start: '2024-01-01T10:00:00Z',
        end: '2024-01-01T11:00:00Z',
        status: 'requested',
      },
    ]
    const state = {
      appointments: {
        providers: [{ id: 1, name: 'Vet' }],
        serviceTypes: [{ id: 1, name: 'Checkup' }],
        appointments,
      },
    }

    const dispatch = vi.fn((action: any) => {
      if (typeof action === 'function') {
        return action(dispatch, () => state, undefined)
      }
      return action
    })

    const store = { getState: () => state, dispatch, subscribe: vi.fn() }

    render(createElement(Provider, { store }, createElement(AppointmentAdmin)))

    expect(screen.getAllByText('Vet').length).toBeGreaterThan(0)

    fireEvent.click(screen.getByText('appointments.confirm'))
    await waitFor(() => expect(confirmMock).toHaveBeenCalledWith(1))

    fireEvent.click(screen.getByText('appointments.complete'))
    await waitFor(() => expect(completeMock).toHaveBeenCalledWith(1))

    fireEvent.click(screen.getByText('appointments.cancel'))
    await waitFor(() => expect(cancelMock).toHaveBeenCalledWith(1))
  })
})

