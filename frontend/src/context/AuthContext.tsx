import {
  createContext,
  useContext,
  useEffect,
  useMemo,
  useState,
  useCallback,
  type ReactNode,
} from 'react'

const normalizeBaseUrl = (value: string | undefined): string => {
  if (!value) return ''
  return value.endsWith('/') ? value.slice(0, -1) : value
}

const normalizeRole = (value: unknown): RoleName | null => {
  if (typeof value !== 'string') {
    return null
  }

  const direct = value.toLowerCase()
  if (direct === 'admin' || direct === 'staff' || direct === 'customer') {
    return direct
  }

  if (value.startsWith('ROLE_')) {
    const derived = value.slice(5).toLowerCase()
    if (derived === 'admin' || derived === 'staff' || derived === 'customer') {
      return derived
    }
  }

  return null
}

export type RoleName = 'admin' | 'staff' | 'customer'

export interface User {
  id: string | number
  name?: string
  email: string
  role?: string | null
  firstName?: string | null
  lastName?: string | null
  roles?: string[] | null
  [key: string]: unknown
}

export interface AuthRefreshHints {
  role?: string | null
  roles?: string[] | null
}

export interface AuthContextType {
  user: User | null
  token: string | null
  role: RoleName | null
  isAuthenticated: boolean
  loading: boolean
  isLoading: boolean
  initialized: boolean
  error: Error | null
  login: (email: string, password: string) => Promise<boolean>
  logout: () => Promise<void>
  refresh: (hints?: AuthRefreshHints) => Promise<void>
  hydrate: (
    token?: string | null,
    user?: User | null,
    hints?: AuthRefreshHints,
  ) => Promise<void>
}

interface AuthProviderProps {
  children: ReactNode
}

const AuthContext = createContext<AuthContextType | undefined>(undefined)

const API_BASE = normalizeBaseUrl(import.meta.env.VITE_RANCH_API_URL) || '/api'

async function parseJson<T>(response: Response): Promise<T> {
  const text = await response.text()
  try {
    return JSON.parse(text) as T
  } catch (error) {
    throw new Error('Unable to parse authentication response')
  }
}

export function AuthProvider({ children }: AuthProviderProps) {
  const [user, setUser] = useState<User | null>(null)
  const [token, setToken] = useState<string | null>(null)
  const [role, setRole] = useState<RoleName | null>(null)
  const [loading, setLoading] = useState(false)
  const [initialized, setInitialized] = useState(false)
  const [error, setError] = useState<Error | null>(null)

  useEffect(() => {
    if (typeof window !== 'undefined') {
      window.localStorage.removeItem('ranch_token')
    }
  }, [])

  const applyUser = useCallback((nextUser: User | null, hints?: AuthRefreshHints) => {
    setUser(nextUser)
    const hintRole = normalizeRole(hints?.role)
    const hintRoles = hints?.roles?.map(normalizeRole).find((r): r is RoleName => !!r)
    const derivedRole =
      hintRole ??
      hintRoles ??
      (nextUser?.role ? normalizeRole(nextUser.role) : null) ??
      (nextUser?.roles && nextUser.roles.length > 0
        ? normalizeRole(nextUser.roles[0])
        : null)

    setRole(derivedRole ?? null)
  }, [])

  const refresh = useCallback(
    async (hints?: AuthRefreshHints) => {
      setLoading(true)
      setError(null)
      try {
        const response = await fetch(`${API_BASE}/me`, {
          credentials: 'include',
        })

        if (response.status === 401) {
          applyUser(null)
          setToken(null)
          return
        }

        if (!response.ok) {
          throw new Error('Session could not be restored')
        }

        const data = await parseJson<User>(response)
        setToken(null)
        applyUser(data, hints ?? { roles: Array.isArray(data.roles) ? data.roles : null })
      } catch (err) {
        applyUser(null)
        setToken(null)
        setError(err instanceof Error ? err : new Error('Unable to refresh authentication'))
        throw err
      } finally {
        setLoading(false)
        setInitialized(true)
      }
    },
    [applyUser],
  )

  const login = useCallback(
    async (email: string, password: string) => {
      setLoading(true)
      setError(null)
      try {
        const response = await fetch(`${API_BASE}/login`, {
          method: 'POST',
          headers: {
            'Content-Type': 'application/json',
          },
          credentials: 'include',
          body: JSON.stringify({ email, password }),
        })

        if (!response.ok) {
          setError(new Error('Invalid credentials'))
          return false
        }

        const data = await parseJson<{
          role?: string | null
          roles?: string[] | null
        }>(response)

        setToken(null)
        await refresh({ role: data.role, roles: data.roles ?? null })
        return true
      } catch (err) {
        setError(err instanceof Error ? err : new Error('Unable to login'))
        return false
      } finally {
        setLoading(false)
      }
    },
    [refresh],
  )

  const hydrate = useCallback(
    async (nextToken?: string | null, nextUser?: User | null, hints?: AuthRefreshHints) => {
      setToken(nextToken ?? null)
      if (nextUser) {
        applyUser(nextUser, hints)
        setInitialized(true)
        return
      }
      await refresh(hints)
    },
    [applyUser, refresh],
  )

  const logout = useCallback(async () => {
    setToken(null)
    applyUser(null)
    setError(null)
    setInitialized(true)
    try {
      await fetch(`${API_BASE}/logout`, {
        method: 'POST',
        credentials: 'include',
      })
    } catch (err) {
      // best effort logout; swallow network errors to avoid breaking UI logout
    }
  }, [applyUser])

  useEffect(() => {
    if (!initialized) {
      refresh().catch(() => undefined)
    }
  }, [initialized, refresh])

  const value = useMemo<AuthContextType>(() => {
    const isAuthenticated = !!user
    return {
      user,
      token,
      role,
      isAuthenticated,
      loading,
      isLoading: loading,
      initialized,
      error,
      login,
      logout,
      refresh,
      hydrate,
    }
  }, [user, token, role, loading, initialized, error, login, logout, refresh, hydrate])

  return <AuthContext.Provider value={value}>{children}</AuthContext.Provider>
}

export function useAuth(): AuthContextType {
  const ctx = useContext(AuthContext)
  if (!ctx) {
    throw new Error('AuthContext not found')
  }
  return ctx
}

export { AuthContext }
