import api from '../axios'

export type RegisterPayload = {
  email: string
  password: string
  firstName: string
  lastName: string
  role?: 'admin' | 'staff' | 'customer'
  roles?: string[]
}

export async function register(payload: RegisterPayload) {
  const response = await api.post('/register', payload)
  return response.data
}

export async function login(email: string, password: string) {
  const response = await api.post('/login', { email, password })
  return response.data
}

export async function acceptInvite(token: string, password: string) {
  const response = await api.post(`/accept-invite/${token}`, { password })
  return response.data
}
