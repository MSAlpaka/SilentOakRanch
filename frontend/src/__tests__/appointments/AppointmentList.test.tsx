import { describe, it, expect, afterEach, vi } from 'vitest'
import { render, screen, cleanup } from '@testing-library/react'
import { Provider } from 'react-redux'
import { createElement } from 'react'
import AppointmentList from '../../modules/appointments/AppointmentList'

vi.mock('react-i18next', () => ({
  useTranslation: () => ({ t: (s: string) => s }),
}))

describe('AppointmentList', () => {
  afterEach(() => {
    cleanup()
  })

  it('renders appointments for owner', () => {
    const appointments = [
      {
        id: 1,
        provider: { id: 1, name: 'Vet' },
        serviceType: { id: 1, name: 'Checkup' },
        start: '2024-01-01T10:00:00Z',
        status: 'confirmed',
        notes: 'Bring records',
      },
      {
        id: 2,
        provider: { id: 1, name: 'Vet' },
        serviceType: { id: 2, name: 'Surgery' },
        start: '2024-02-01T11:00:00Z',
        status: 'pending',
        notes: 'N/A',
      },
    ]
    const state = {
      appointments: {
        appointments,
      },
    }
    const store = { getState: () => state, dispatch: vi.fn(), subscribe: vi.fn() }

    render(createElement(Provider, { store }, createElement(AppointmentList)))

    expect(screen.getAllByText('Vet').length).toBe(2)
    expect(screen.getByText('Checkup')).toBeTruthy()
    expect(screen.getByText('Surgery')).toBeTruthy()
    expect(screen.getByText('confirmed')).toBeTruthy()
    expect(screen.getByText('pending')).toBeTruthy()
  })
})

