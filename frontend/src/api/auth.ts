import api from '../axios'

export type AuthUser = {
  id: string | number
  name?: string
  email: string
  role?: string | null
  roles?: string[] | null
}

export type RegisterPayload = {
  email: string
  password: string
  firstName: string
  lastName: string
  role?: 'admin' | 'staff' | 'customer'
  roles?: string[]
}

export type AuthMetadata = {
  ok?: boolean
  token?: string
  user?: AuthUser | null
  role?: string | null
  roles?: string[]
  expiresAt?: string
  expiresIn?: number
}

export async function register(payload: RegisterPayload): Promise<AuthMetadata> {
  const response = await api.post('/register', payload)
  return response.data
}

export async function login(email: string, password: string): Promise<AuthMetadata> {
  const response = await api.post('/auth/login', { email, password })
  return response.data
}

export async function inviteUser(email: string) {
  const response = await api.post('/invite', { email })
  return response.data
}

export async function acceptInvite(token: string, password: string): Promise<AuthMetadata> {
  const response = await api.post(`/auth/accept-invite/${token}`, { password })
  return response.data
}
