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

describe('AuthContext', () => {
  beforeEach(() => {
    vi.clearAllMocks()
    postMock.mockReset()
    getMock.mockReset()
    postMock.mockResolvedValue({ data: {} })
    getMock.mockRejectedValue({ response: { status: 401 } })
  })

  afterEach(() => {
    cleanup()
  })

  it('loads user info on successful login and resolves roles', async () => {
    const user: UserInfo = {
      id: 1,
      email: 'john@example.com',
      roles: ['ROLE_CUSTOMER'],
      firstName: 'John',
      lastName: 'Doe',
    }

    let contextValue: AuthContextValue | undefined

    render(
      <AuthProvider>
        <TestConsumer onReady={value => (contextValue = value)} />
      </AuthProvider>
    )

    await waitFor(() => expect(contextValue?.initialized).toBe(true))

    if (!contextValue) {
      throw new Error('Auth context not ready')
    }

    postMock.mockResolvedValueOnce({ data: { role: 'customer', roles: ['ROLE_CUSTOMER'] } })
    getMock.mockResolvedValueOnce({ data: user })

    await act(async () => {
      await contextValue!.login('john@example.com', 'secret123')
    })

    await waitFor(() => {
      expect(contextValue?.role).toBe('customer')
      expect(contextValue?.user).toEqual(user)
      expect(contextValue?.isAuthenticated).toBe(true)
    })

    expect(postMock).toHaveBeenCalledWith('/login', {
      email: 'john@example.com',
      password: 'secret123',
    })
    expect(getMock).toHaveBeenCalledWith('/me')
  })

  it('falls back to /me roles when not returned from login metadata', async () => {
    const user: UserInfo = {
      id: 2,
      email: 'admin@example.com',
      roles: ['ROLE_ADMIN'],
    }

    let contextValue: AuthContextValue | undefined

    render(
      <AuthProvider>
        <TestConsumer onReady={value => (contextValue = value)} />
      </AuthProvider>
    )

    await waitFor(() => expect(contextValue?.initialized).toBe(true))

    if (!contextValue) {
      throw new Error('Auth context not ready')
    }

    postMock.mockResolvedValueOnce({ data: {} })
    getMock.mockResolvedValueOnce({ data: user })

    await act(async () => {
      await contextValue!.login('admin@example.com', 'secret123')
    })

    await waitFor(() => {
      expect(contextValue?.role).toBe('admin')
    })
  })

  it('calls logout endpoint and clears user state', async () => {
    const user: UserInfo = {
      id: 3,
      email: 'user@example.com',
      roles: ['ROLE_CUSTOMER'],
    }

    let contextValue: AuthContextValue | undefined

    render(
      <AuthProvider>
        <TestConsumer onReady={value => (contextValue = value)} />
      </AuthProvider>
    )

    await waitFor(() => expect(contextValue?.initialized).toBe(true))

    if (!contextValue) {
      throw new Error('Auth context not ready')
    }

    postMock.mockResolvedValueOnce({ data: { role: 'customer' } })
    getMock.mockResolvedValueOnce({ data: user })

    await act(async () => {
      await contextValue!.login('user@example.com', 'secret123')
    })

    await waitFor(() => expect(contextValue?.isAuthenticated).toBe(true))

    postMock.mockResolvedValueOnce({ data: { message: 'Logged out' } })

    await act(async () => {
      await contextValue!.logout()
    })

    expect(postMock).toHaveBeenLastCalledWith('/logout')
    expect(contextValue?.user).toBeNull()
    expect(contextValue?.isAuthenticated).toBe(false)
    expect(contextValue?.role).toBeNull()
  })
})
