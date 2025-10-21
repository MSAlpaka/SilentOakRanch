import { describe, it, expect, vi, beforeEach, afterEach } from 'vitest'
import { render, screen, fireEvent, waitFor, cleanup } from '@testing-library/react'

import InviteAccept from '../InviteAccept'

const navigateMock = vi.fn()
const acceptInviteMock = vi.fn()
const hydrateMock = vi.fn()

vi.mock('react-router-dom', () => ({
  useNavigate: () => navigateMock,
  useSearchParams: () => [new URLSearchParams('token=my-token'), vi.fn()],
}))

vi.mock('@/api/auth', () => ({
  acceptInvite: (...args: any[]) => acceptInviteMock(...args),
}))

vi.mock('@/hooks/useAuth', () => ({
  useAuth: () => ({ hydrate: hydrateMock }),
}))

describe('InviteAccept', () => {
  beforeEach(() => {
    navigateMock.mockReset()
    acceptInviteMock.mockReset()
    hydrateMock.mockReset()
  })

  afterEach(() => {
    cleanup()
  })

  it('submits password, hydrates auth and navigates', async () => {
    acceptInviteMock.mockResolvedValue({ ok: true, token: 'api-token', user: { id: 1, email: 'guest@example.com' } })

    render(<InviteAccept />)

    fireEvent.change(screen.getByLabelText(/password/i), {
      target: { value: 'new-secret' },
    })
    fireEvent.click(screen.getByRole('button', { name: /accept invite/i }))

    await waitFor(() => {
      expect(acceptInviteMock).toHaveBeenCalledWith('my-token', 'new-secret')
      expect(hydrateMock).toHaveBeenCalledWith('api-token', { id: 1, email: 'guest@example.com' })
      expect(navigateMock).toHaveBeenCalledWith('/dashboard/bookings', { replace: true })
    })
  })
})
