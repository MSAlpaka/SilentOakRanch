import {
  createContext,
  useContext,
  useState,
  type ReactNode,
  useEffect,
  useCallback,
} from 'react'
import api from '../../axios'
import { UserInfo } from './useUser'

type RoleName = 'admin' | 'staff' | 'customer'

type AuthHints = {
  role?: string | null
  roles?: string[]
}

type AuthState = {
  user: UserInfo | null
  role: RoleName | null
  token: string | null
  isAuthenticated: boolean
  isLoading: boolean
  initialized: boolean
  error: Error | null
  login: (email: string, password: string) => Promise<void>
  logout: () => Promise<void>
  refresh: (hints?: AuthHints) => Promise<void>
}

const STORAGE_KEY = 'sor.authToken'

function normalizeRole(value: unknown): RoleName | null {
  if (typeof value !== 'string') return null

  const lower = value.toLowerCase()

  if (lower === 'admin' || lower === 'staff' || lower === 'customer') {
    return lower as RoleName
  }

  if (value.startsWith('ROLE_')) {
    const normalized = value.slice(5).toLowerCase()
    if (normalized === 'admin' || normalized === 'staff' || normalized === 'customer') {
      return normalized as RoleName
    }
  }

  return null
}

function isUnauthorizedError(error: unknown): boolean {
  if (typeof error !== 'object' || error === null) {
    return false
  }

  const response = (error as { response?: { status?: number } }).response
  return typeof response?.status === 'number' && response.status === 401
}

const AuthContext = createContext<AuthState | undefined>(undefined)

export function AuthProvider({ children }: { children: ReactNode }) {
  const [user, setUser] = useState<UserInfo | null>(null)
  const [role, setRole] = useState<RoleName | null>(null)
  const [token, setToken] = useState<string | null>(() => {
    if (typeof window === 'undefined') return null
    return window.localStorage.getItem(STORAGE_KEY)
  })
  const [isLoading, setIsLoading] = useState(true)
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

  const refresh = useCallback(async (hints?: AuthHints) => {
    setIsLoading(true)
    try {
      const me = await api.get('/me')
      const meData = me.data as UserInfo

      const resolvedRole =
        normalizeRole(hints?.role) ??
        normalizeRole(hints?.roles?.[0]) ??
        normalizeRole(meData.roles?.[0]) ??
        null

      setUser(meData)
      setRole(resolvedRole)
      setError(null)
    } catch (err) {
      setUser(null)
      setRole(null)
      persistToken(null)
      setToken(null)
      if (isUnauthorizedError(err)) {
        setError(null)
      } else {
        setError(err instanceof Error ? err : new Error('Authentication refresh failed'))
      }
      throw err
    } finally {
      setIsLoading(false)
      setInitialized(true)
    }
  }, [persistToken])

  useEffect(() => {
    refresh().catch(() => undefined)
  }, [refresh])

  const login = useCallback(
    async (email: string, password: string) => {
      const response = await api.post('/login', { email, password })
      const {
        role: responseRole,
        roles: responseRoles,
        token: responseToken,
        access_token: accessToken,
      }: {
        role?: string | null
        roles?: string[]
        token?: string
        access_token?: string
      } = response.data ?? {}

      const nextToken = responseToken ?? accessToken ?? null
      if (nextToken) {
        setToken(nextToken)
        persistToken(nextToken)
      }

      await refresh({ role: responseRole, roles: responseRoles })
    },
    [refresh, persistToken],
  )

  const logout = useCallback(async () => {
    try {
      await api.post('/logout')
    } finally {
      setUser(null)
      setRole(null)
      setToken(null)
      persistToken(null)
      setInitialized(true)
      setIsLoading(false)
      setError(null)
    }
  }, [persistToken])

  useEffect(() => {
    if (!token) {
      const stored = typeof window !== 'undefined' ? window.localStorage.getItem(STORAGE_KEY) : null
      if (stored) {
        setToken(stored)
      }
    }
  }, [token])

  return (
    <AuthContext.Provider
      value={{
        user,
        role,
        token,
        isAuthenticated: !!user,
        isLoading,
        initialized,
        error,
        login,
        logout,
        refresh,
      }}
    >
      {children}
    </AuthContext.Provider>
  )
}

export function useAuth() {
  const ctx = useContext(AuthContext)
  if (!ctx) throw new Error('AuthContext not found')
  return ctx
}
