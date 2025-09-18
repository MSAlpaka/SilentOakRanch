import { createContext, useContext, useState, ReactNode } from 'react'
import api from '../../axios'
import { UserInfo } from './useUser'

type RoleName = 'admin' | 'staff' | 'customer'

type AuthState = {
  user: UserInfo | null
  role: RoleName | null
  token: string | null
  login: (email: string, password: string) => Promise<void>
  logout: () => void
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

const AuthContext = createContext<AuthState | undefined>(undefined)

export function AuthProvider({ children }: { children: ReactNode }) {
  const [user, setUser] = useState<UserInfo | null>(() => {
    const raw = localStorage.getItem('user')
    return raw ? (JSON.parse(raw) as UserInfo) : null
  })
  const [role, setRole] = useState<RoleName | null>(() =>
    (localStorage.getItem('role') as RoleName | null) || null
  )
  const [token, setToken] = useState<string | null>(() =>
    localStorage.getItem('token')
  )

  async function login(email: string, password: string) {
    const response = await api.post('/login', { email, password })
    const {
      token: newToken,
      role: responseRole,
      roles: responseRoles,
    }: {
      token: string
      role?: string | null
      roles?: string[]
    } = response.data

    try {
      const me = await api.get('/me', {
        headers: { Authorization: `Bearer ${newToken}` },
      })

      const meData = me.data as UserInfo
      const resolvedRole =
        normalizeRole(responseRole) ??
        normalizeRole(responseRoles?.[0]) ??
        normalizeRole(meData.roles?.[0])

      setUser(meData)
      setToken(newToken)
      setRole(resolvedRole)
      localStorage.setItem('token', newToken)
      if (resolvedRole) {
        localStorage.setItem('role', resolvedRole)
      } else {
        localStorage.removeItem('role')
      }
      localStorage.setItem('user', JSON.stringify(meData))
    } catch (err) {
      setToken(null)
      setRole(null)
      localStorage.removeItem('token')
      localStorage.removeItem('role')
      throw err
    }
  }

  function logout() {
    setUser(null)
    setToken(null)
    setRole(null)
    localStorage.removeItem('user')
    localStorage.removeItem('token')
    localStorage.removeItem('role')
  }

  return (
    <AuthContext.Provider value={{ user, role, token, login, logout }}>
      {children}
    </AuthContext.Provider>
  )
}

export function useAuth() {
  const ctx = useContext(AuthContext)
  if (!ctx) throw new Error('AuthContext not found')
  return ctx
}
