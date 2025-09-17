import { createContext, useContext, useState, ReactNode } from 'react'
import api from '../../axios'
import { UserInfo } from './useUser'

type AuthState = {
  user: UserInfo | null
  role: 'admin' | 'staff' | 'customer' | null
  token: string | null
  login: (email: string, password: string) => Promise<void>
  logout: () => void
}

const AuthContext = createContext<AuthState | undefined>(undefined)

export function AuthProvider({ children }: { children: ReactNode }) {
  const [user, setUser] = useState<UserInfo | null>(() => {
    const raw = localStorage.getItem('user')
    return raw ? (JSON.parse(raw) as UserInfo) : null
  })
  const [role, setRole] = useState<'admin' | 'staff' | 'customer' | null>(() =>
    (localStorage.getItem('role') as 'admin' | 'staff' | 'customer' | null) ||
    null
  )
  const [token, setToken] = useState<string | null>(() =>
    localStorage.getItem('token')
  )

  async function login(email: string, password: string) {
    const response = await api.post('/login', { email, password })
    const { token: newToken, role: newRole } = response.data

    try {
      const me = await api.get('/me', {
        headers: { Authorization: `Bearer ${newToken}` },
      })

      setUser(me.data as UserInfo)
      setToken(newToken)
      setRole(newRole)
      localStorage.setItem('token', newToken)
      localStorage.setItem('role', newRole)
      localStorage.setItem('user', JSON.stringify(me.data))
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
