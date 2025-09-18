import { createContext, useContext, useState, ReactNode, useEffect, useCallback } from 'react'
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
  isAuthenticated: boolean
  isLoading: boolean
  initialized: boolean
  error: Error | null
  login: (email: string, password: string) => Promise<void>
  logout: () => Promise<void>
  refresh: (hints?: AuthHints) => Promise<void>
}

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
  const [isLoading, setIsLoading] = useState(true)
  const [initialized, setInitialized] = useState(false)
  const [error, setError] = useState<Error | null>(null)

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
  }, [])

  useEffect(() => {
    refresh().catch(() => undefined)
  }, [refresh])

  const login = useCallback(
    async (email: string, password: string) => {
      const response = await api.post('/login', { email, password })
      const { role: responseRole, roles: responseRoles }: { role?: string | null; roles?: string[] } =
        response.data ?? {}
      await refresh({ role: responseRole, roles: responseRoles })
    },
    [refresh]
  )

  const logout = useCallback(async () => {
    try {
      await api.post('/logout')
    } finally {
      setUser(null)
      setRole(null)
      setInitialized(true)
      setIsLoading(false)
      setError(null)
    }
  }, [])

  return (
    <AuthContext.Provider
      value={{
        user,
        role,
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
