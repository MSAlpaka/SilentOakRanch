import { describe, it, expect, vi, beforeEach, afterEach } from 'vitest'
import { render, screen, fireEvent, waitFor, cleanup } from '@testing-library/react'
import Register from '../Register'

vi.mock('react-i18next', () => ({
  useTranslation: () => ({ t: (s: string) => s }),
}))

const mockNavigate = vi.fn()
vi.mock('react-router-dom', () => ({
  useNavigate: () => mockNavigate,
}))

const registerMock = vi.fn()
vi.mock('../../api/auth', () => ({
  register: (...args: any[]) => registerMock(...args),
}))

const consentMock = vi.fn()
vi.mock('../../api/agreements', () => ({
  giveConsent: (...args: any[]) => consentMock(...args),
}))

describe('Register', () => {
  beforeEach(() => {
    registerMock.mockReset()
    consentMock.mockReset()
    mockNavigate.mockReset()
  })

  afterEach(() => {
    cleanup()
  })

  it('submits consents after registration', async () => {
    registerMock.mockResolvedValue({})
    consentMock.mockResolvedValue({})

    render(<Register />)

    fireEvent.change(screen.getByPlaceholderText('auth.register.first_name'), {
      target: { value: 'John' },
    })
    fireEvent.change(screen.getByPlaceholderText('auth.register.last_name'), {
      target: { value: 'Doe' },
    })
    fireEvent.change(screen.getByPlaceholderText('auth.register.email'), {
      target: { value: 'john@example.com' },
    })
    fireEvent.change(screen.getByPlaceholderText('auth.register.password'), {
      target: { value: 'secret' },
    })
    fireEvent.click(screen.getByLabelText('auth.register.terms'))
    fireEvent.click(screen.getByLabelText('auth.register.privacy'))
    fireEvent.click(screen.getByRole('button'))

    await waitFor(() => {
      expect(registerMock).toHaveBeenCalledWith({
        firstName: 'John',
        lastName: 'Doe',
        email: 'john@example.com',
        password: 'secret',
        role: 'customer',
      })
      expect(consentMock).toHaveBeenNthCalledWith(1, 'agb')
      expect(consentMock).toHaveBeenNthCalledWith(2, 'privacy')
      expect(mockNavigate).toHaveBeenCalledWith('/login')
    })
  })

  it('requires both consents', async () => {
    render(<Register />)

    fireEvent.click(screen.getByRole('button'))

    expect(screen.getByText('auth.register.terms_required')).toBeTruthy()
    expect(screen.getByText('auth.register.privacy_required')).toBeTruthy()
    expect(registerMock).not.toHaveBeenCalled()
    expect(consentMock).not.toHaveBeenCalled()
  })

  it('validates required fields before submitting', async () => {
    render(<Register />)

    fireEvent.click(screen.getByLabelText('auth.register.terms'))
    fireEvent.click(screen.getByLabelText('auth.register.privacy'))
    fireEvent.click(screen.getByRole('button'))

    expect(screen.getByText('auth.register.missing_fields')).toBeTruthy()
    expect(registerMock).not.toHaveBeenCalled()
  })
})
