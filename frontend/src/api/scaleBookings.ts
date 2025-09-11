import api from '../axios'

export async function getSlots(day?: string) {
  const response = await api.get('/scale/slots', { params: day ? { day } : undefined })
  return response.data
}

export interface CreateScaleBookingPayload {
  horseId: string
  slot: string
  type: string
}

export async function create(payload: CreateScaleBookingPayload) {
  const response = await api.post('/scale/bookings', payload)
  return response.data
}

export async function confirm(id: string) {
  const response = await api.post(`/scale/bookings/${id}/confirm`)
  return response.data
}

export async function setWeight(id: string, weight: number) {
  const response = await api.post(`/scale/bookings/${id}/weight`, { weight })
  return response.data
}
