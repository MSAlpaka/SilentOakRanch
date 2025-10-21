import { describe, it, expect, vi, beforeEach, afterEach } from 'vitest'
import { cleanup, render, waitFor, act } from '@testing-library/react'

import { AuthProvider, useAuth } from './AuthContext'

type AuthContextValue = ReturnType<typeof useAuth>

declare global {
  // eslint-disable-next-line @typescript-eslint/no-namespace
  namespace NodeJS {
    interface Global {
      fetch: typeof fetch
    }
  }
}

const fetchMock = vi.fn()
const originalFetch = global.fetch

function mockResponse(
  data: unknown,
  {
    ok = true,
    status = ok ? 200 : 400,
  }: {
    ok?: boolean
    status?: number
  } = {},
) {
  return {
    ok,
    status,
    text: () => Promise.resolve(JSON.stringify(data)),
  } as Response
}

function TestConsumer({ onReady }: { onReady: (value: AuthContextValue) => void }) {
  const value = useAuth()
  onReady(value)
  return null
}

describe('AuthContext', () => {
  beforeEach(() => {
    fetchMock.mockReset()
    // @ts-expect-error override for test
    global.fetch = fetchMock
    window.localStorage.clear()
  })

  afterEach(() => {
    cleanup()
    global.fetch = originalFetch
  })

  it('hydrates session with provided user data', async () => {
    fetchMock.mockResolvedValueOnce(
      mockResponse({ message: 'Unauthorized' }, { ok: false, status: 401 }),
    )

    let contextValue: AuthContextValue | undefined

    render(
      <AuthProvider>
        <TestConsumer onReady={(value) => (contextValue = value)} />
      </AuthProvider>,
    )

    await waitFor(() => expect(contextValue?.initialized).toBe(true))

    if (!contextValue) {
      throw new Error('Context not initialized')
    }

    await act(async () => {
      await contextValue!.hydrate(undefined, {
        id: 1,
        email: 'john@example.com',
        roles: ['ROLE_STAFF'],
      })
    })

    expect(contextValue.user?.email).toBe('john@example.com')
    expect(contextValue.role).toBe('staff')
    expect(contextValue.isAuthenticated).toBe(true)
  })

  it('restores user from API session cookie', async () => {
    fetchMock.mockResolvedValueOnce(
      mockResponse({ id: 2, email: 'restored@example.com', roles: ['ROLE_STAFF'] }),
    )

    let contextValue: AuthContextValue | undefined

    render(
      <AuthProvider>
        <TestConsumer onReady={(value) => (contextValue = value)} />
      </AuthProvider>,
    )

    await waitFor(() => expect(contextValue?.user?.email).toBe('restored@example.com'))

    expect(fetchMock).toHaveBeenCalledWith('/api/me', {
      credentials: 'include',
    })
    expect(contextValue?.role).toBe('staff')
    expect(contextValue?.isAuthenticated).toBe(true)
  })

  it('clears session on logout', async () => {
    fetchMock.mockResolvedValueOnce(
      mockResponse({ message: 'Unauthorized' }, { ok: false, status: 401 }),
    )
    fetchMock.mockResolvedValueOnce(mockResponse({ message: 'Logged out' }))

    let contextValue: AuthContextValue | undefined

    render(
      <AuthProvider>
        <TestConsumer onReady={(value) => (contextValue = value)} />
      </AuthProvider>,
    )

    await waitFor(() => expect(contextValue?.initialized).toBe(true))

    if (!contextValue) {
      throw new Error('Context not initialized')
    }

    await act(async () => {
      await contextValue!.hydrate(undefined, {
        id: 3,
        email: 'logout@example.com',
        roles: ['ROLE_ADMIN'],
      })
    })

    expect(contextValue.user).not.toBeNull()

    await act(async () => {
      await contextValue!.logout()
    })

    expect(contextValue.user).toBeNull()
    expect(fetchMock).toHaveBeenCalledWith('/api/logout', {
      credentials: 'include',
      method: 'POST',
    })
  })
})
