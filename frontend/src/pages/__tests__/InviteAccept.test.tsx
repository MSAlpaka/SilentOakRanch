import { describe, it, expect, vi, beforeEach, afterEach } from 'vitest'
import { render, screen, fireEvent, waitFor, cleanup } from '@testing-library/react'
import InviteAccept from '../InviteAccept'
import { setToken } from '../../modules/auth/authSlice'

const navigateMock = vi.fn()
const dispatchMock = vi.fn()
const acceptInviteMock = vi.fn()

vi.mock('react-i18next', () => ({
  useTranslation: () => ({ t: (s: string) => s }),
}))

vi.mock('react-router-dom', () => ({
  useNavigate: () => navigateMock,
  useSearchParams: () => [new URLSearchParams('token=my-token'), vi.fn()],
}))

vi.mock('../../api/auth', () => ({
  acceptInvite: (...args: any[]) => acceptInviteMock(...args),
}))

vi.mock('../../store', () => ({
  useAppDispatch: () => dispatchMock,
}))

describe('InviteAccept', () => {
  beforeEach(() => {
    navigateMock.mockReset()
    dispatchMock.mockReset()
    acceptInviteMock.mockReset()
  })

  afterEach(() => {
    cleanup()
  })

  it('submits password and stores token', async () => {
    acceptInviteMock.mockResolvedValue({ token: 'jwt-token' })

    render(<InviteAccept />)

    fireEvent.change(screen.getByPlaceholderText('auth.invite.new_password'), {
      target: { value: 'new-secret' },
    })
    fireEvent.click(screen.getByRole('button'))

    await waitFor(() => {
      expect(acceptInviteMock).toHaveBeenCalledWith('my-token', 'new-secret')
      expect(dispatchMock).toHaveBeenCalledWith(setToken('jwt-token'))
      expect(navigateMock).toHaveBeenCalledWith('/dashboard')
    })
  })
})
