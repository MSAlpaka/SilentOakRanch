import api from '../axios'

export async function register(username: string, password: string) {
  const response = await api.post('/register', { username, password })
  return response.data
}

export async function login(username: string, password: string) {
  const response = await api.post('/login', { username, password })
  return response.data
}

export async function acceptInvite(token: string, password: string) {
  const response = await api.post('/accept-invite', { token, password })
  return response.data
}
