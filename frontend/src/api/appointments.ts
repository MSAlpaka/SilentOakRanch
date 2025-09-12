import api from '../axios'

export interface Provider {
  id: number
  name: string
}

export interface ServiceType {
  id: number
  name: string
}

export interface Appointment {
  id: number
  provider: Provider
  serviceType: ServiceType
  start: string
  status: string
  notes?: string
  reminderOptIn?: boolean
}

export interface CreateAppointmentPayload {
  providerId: number
  serviceTypeId: number
  start: string
  notes?: string
  reminderOptIn?: boolean
}

export async function getProviders() {
  const res = await api.get<Provider[]>('/appointments/providers')
  return res.data
}

export async function getServiceTypes() {
  const res = await api.get<ServiceType[]>('/appointments/service-types')
  return res.data
}

export async function getAppointments() {
  const res = await api.get<Appointment[]>('/appointments')
  return res.data
}

export async function createAppointment(payload: CreateAppointmentPayload) {
  const res = await api.post<Appointment>('/appointments', payload)
  return res.data
}

export async function confirmAppointment(id: number) {
  const res = await api.post<Appointment>(`/appointments/${id}/confirm`)
  return res.data
}

export async function completeAppointment(id: number) {
  const res = await api.post<Appointment>(`/appointments/${id}/complete`)
  return res.data
}

export async function cancelAppointment(id: number) {
  const res = await api.post<Appointment>(`/appointments/${id}/cancel`)
  return res.data
}

