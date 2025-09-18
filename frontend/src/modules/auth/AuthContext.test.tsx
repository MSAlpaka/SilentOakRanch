import { describe, it, expect, beforeEach, afterEach, vi } from 'vitest'
import { render, cleanup, waitFor, act } from '@testing-library/react'
import { AuthProvider, useAuth } from './AuthContext'
import type { UserInfo } from './useUser'

const postMock = vi.hoisted(() => vi.fn())
const getMock = vi.hoisted(() => vi.fn())

vi.mock('../../axios', () => ({
  default: {
    post: postMock,
    get: getMock,
  },
}))

type AuthContextValue = ReturnType<typeof useAuth>

function TestConsumer({ onReady }: { onReady: (value: AuthContextValue) => void }) {
  const value = useAuth()
  onReady(value)
  return null
}

describe('AuthContext login', () => {
  beforeEach(() => {
    vi.clearAllMocks()
    postMock.mockReset()
    getMock.mockReset()
    localStorage.clear()
  })

  afterEach(() => {
    cleanup()
  })

  it('stores token and provided role on successful login', async () => {
    const user: UserInfo = {
      id: 1,
      email: 'john@example.com',
      roles: ['ROLE_CUSTOMER'],
      firstName: 'John',
      lastName: 'Doe',
    }

    postMock.mockResolvedValue({
      data: { token: 'jwt-token', role: 'customer', roles: ['ROLE_CUSTOMER'] },
    })
    getMock.mockResolvedValue({ data: user })

    let contextValue: AuthContextValue | undefined

    render(
      <AuthProvider>
        <TestConsumer onReady={value => (contextValue = value)} />
      </AuthProvider>
    )

    if (!contextValue) {
      throw new Error('Auth context not ready')
    }

    await act(async () => {
      await contextValue!.login('john@example.com', 'secret123')
    })

    await waitFor(() => {
      expect(contextValue?.role).toBe('customer')
    })

    expect(postMock).toHaveBeenCalledWith('/login', {
      email: 'john@example.com',
      password: 'secret123',
    })
    expect(getMock).toHaveBeenCalledWith('/me', {
      headers: { Authorization: 'Bearer jwt-token' },
    })
    expect(localStorage.getItem('token')).toBe('jwt-token')
    expect(localStorage.getItem('role')).toBe('customer')
    expect(JSON.parse(localStorage.getItem('user') ?? 'null')).toEqual(user)
  })

  it('falls back to role information from /me when not returned from login', async () => {
    const user: UserInfo = {
      id: 2,
      email: 'admin@example.com',
      roles: ['ROLE_ADMIN'],
    }

    postMock.mockResolvedValue({
      data: { token: 'other-token' },
    })
    getMock.mockResolvedValue({ data: user })

    let contextValue: AuthContextValue | undefined

    render(
      <AuthProvider>
        <TestConsumer onReady={value => (contextValue = value)} />
      </AuthProvider>
    )

    if (!contextValue) {
      throw new Error('Auth context not ready')
    }

    await act(async () => {
      await contextValue!.login('admin@example.com', 'secret123')
    })

    await waitFor(() => {
      expect(contextValue?.role).toBe('admin')
    })

    expect(localStorage.getItem('role')).toBe('admin')
    expect(localStorage.getItem('role')).not.toBe('ROLE_ADMIN')
  })
})
