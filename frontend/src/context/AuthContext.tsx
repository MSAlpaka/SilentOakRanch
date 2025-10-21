import {
  createContext,
  useContext,
  useEffect,
  useMemo,
  useState,
  useCallback,
  type ReactNode,
} from 'react'

const STORAGE_KEY = 'ranch_token'

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
  hydrate: (token: string, user?: User | null) => Promise<void>
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
  const [token, setToken] = useState<string | null>(() => {
    if (typeof window === 'undefined') return null
    return window.localStorage.getItem(STORAGE_KEY)
  })
  const [role, setRole] = useState<RoleName | null>(null)
  const [loading, setLoading] = useState(false)
  const [initialized, setInitialized] = useState(false)
  const [error, setError] = useState<Error | null>(null)

  const persistToken = useCallback((value: string | null) => {
    if (typeof window === 'undefined') return
    if (value) {
      window.localStorage.setItem(STORAGE_KEY, value)
    } else {
      window.localStorage.removeItem(STORAGE_KEY)
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
      const activeToken =
        token ?? (typeof window !== 'undefined' ? window.localStorage.getItem(STORAGE_KEY) : null)

      if (!activeToken) {
        applyUser(null)
        setToken(null)
        persistToken(null)
        setInitialized(true)
        return
      }

      setLoading(true)
      setError(null)
      try {
        const response = await fetch(`${API_BASE}/auth/me`, {
          headers: {
            Authorization: `Bearer ${activeToken}`,
          },
        })

        if (!response.ok) {
          throw new Error('Session could not be restored')
        }

        const data = await parseJson<{ ok?: boolean; user?: User | null }>(response)
        if (!data.ok || !data.user) {
          throw new Error('Invalid authentication response')
        }

        setToken(activeToken)
        persistToken(activeToken)
        applyUser(data.user, hints)
      } catch (err) {
        applyUser(null)
        setToken(null)
        persistToken(null)
        setError(err instanceof Error ? err : new Error('Unable to refresh authentication'))
        throw err
      } finally {
        setLoading(false)
        setInitialized(true)
      }
    },
    [applyUser, persistToken, token],
  )

  const login = useCallback(
    async (email: string, password: string) => {
      setLoading(true)
      setError(null)
      try {
        const response = await fetch(`${API_BASE}/auth/login`, {
          method: 'POST',
          headers: {
            'Content-Type': 'application/json',
          },
          body: JSON.stringify({ email, password }),
        })

        if (!response.ok) {
          setError(new Error('Invalid credentials'))
          return false
        }

        const data = await parseJson<{
          ok?: boolean
          token?: string
          user?: User | null
          role?: string | null
          roles?: string[] | null
        }>(response)

        if (!data.token || !data.user) {
          setError(new Error('Invalid credentials'))
          return false
        }

        setToken(data.token)
        persistToken(data.token)
        applyUser(data.user, { role: data.role, roles: data.roles ?? data.user.roles })
        setInitialized(true)
        return true
      } catch (err) {
        setError(err instanceof Error ? err : new Error('Unable to login'))
        return false
      } finally {
        setLoading(false)
      }
    },
    [applyUser, persistToken],
  )

  const hydrate = useCallback(
    async (nextToken: string, nextUser?: User | null) => {
      persistToken(nextToken)
      setToken(nextToken)
      if (nextUser) {
        applyUser(nextUser)
        setInitialized(true)
        return
      }
      await refresh()
    },
    [applyUser, persistToken, refresh],
  )

  const logout = useCallback(async () => {
    persistToken(null)
    setToken(null)
    applyUser(null)
    setError(null)
    setInitialized(true)
  }, [applyUser, persistToken])

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
