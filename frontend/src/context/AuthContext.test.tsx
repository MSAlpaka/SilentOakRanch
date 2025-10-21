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

function mockResponse(data: unknown, ok = true) {
  return {
    ok,
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

  it('hydrates session and stores token', async () => {
    fetchMock.mockResolvedValueOnce(mockResponse({ ok: false }, false))

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
      await contextValue!.hydrate('api-token', { id: 1, email: 'john@example.com' })
    })

    expect(window.localStorage.getItem('ranch_token')).toBe('api-token')
    expect(contextValue.user?.email).toBe('john@example.com')
    expect(contextValue.isAuthenticated).toBe(true)
  })

  it('restores user from stored token', async () => {
    window.localStorage.setItem('ranch_token', 'stored-token')

    fetchMock.mockResolvedValueOnce(
      mockResponse({ ok: true, user: { id: 2, email: 'restored@example.com', role: 'staff' } }),
    )

    let contextValue: AuthContextValue | undefined

    render(
      <AuthProvider>
        <TestConsumer onReady={(value) => (contextValue = value)} />
      </AuthProvider>,
    )

    await waitFor(() => expect(contextValue?.user?.email).toBe('restored@example.com'))

    expect(fetchMock).toHaveBeenCalledWith('/api/auth/me', {
      headers: { Authorization: 'Bearer stored-token' },
    })
    expect(contextValue?.role).toBe('staff')
    expect(contextValue?.isAuthenticated).toBe(true)
  })

  it('clears session on logout', async () => {
    fetchMock.mockResolvedValueOnce(mockResponse({ ok: false }, false))

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
      await contextValue!.hydrate('api-token', { id: 3, email: 'logout@example.com' })
    })

    expect(contextValue.user).not.toBeNull()

    await act(async () => {
      await contextValue!.logout()
    })

    expect(contextValue.user).toBeNull()
    expect(window.localStorage.getItem('ranch_token')).toBeNull()
  })
})
