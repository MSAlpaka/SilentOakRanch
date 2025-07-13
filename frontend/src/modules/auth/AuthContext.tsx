import { createContext, useContext, useState, ReactNode } from 'react'
import api from '../../axios'
import { UserInfo } from './useUser'

type AuthState = {
  user: UserInfo | null
  role: 'admin' | 'staff' | 'customer' | null
  token: string | null
  login: (username: string, password: string) => Promise<void>
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

  async function login(username: string, password: string) {
    const response = await api.post('/login', { username, password })
    const { token, role } = response.data
    setToken(token)
    setRole(role)
    localStorage.setItem('token', token)
    localStorage.setItem('role', role)

    const me = await api.get('/me', {
      headers: { Authorization: `Bearer ${token}` },
    })
    setUser(me.data as UserInfo)
    localStorage.setItem('user', JSON.stringify(me.data))
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
