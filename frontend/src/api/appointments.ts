import api from '../axios'

export interface Provider {
  id: number
  name: string
  type?: string
  contact?: string | null
  notes?: string | null
  active?: boolean
}

export interface ServiceType {
  id: number
  name: string
  providerType?: string
  defaultDurationMinutes?: number
  basePrice?: number
  taxable?: boolean
}

export interface HorseSummary {
  id: number
  name: string
}

export interface OwnerSummary {
  id: number
  name: string
}

export interface Appointment {
  id: number
  horse: HorseSummary
  owner: OwnerSummary
  provider: Provider | null
  serviceType: ServiceType
  start: string
  end: string
  status: string
  price?: number | null
  notes?: string | null
}

export interface CreateAppointmentPayload {
  serviceTypeId: number
  start: string
  end: string
  providerId?: number
  horseId?: number
  notes?: string
  reminderOptIn?: boolean
}

type ProviderResponse = {
  id: number
  name: string
  type?: string
  contact?: string | null
  notes?: string | null
  active?: boolean
}

type ServiceTypeResponse = {
  id: number
  name: string
  providerType?: string
  defaultDurationMinutes?: number
  basePrice?: number
  taxable?: boolean
}

type AppointmentResponse = {
  id: number
  horse: HorseSummary
  owner: OwnerSummary
  serviceProvider: ProviderResponse | null
  serviceType: ServiceTypeResponse
  startTime: string
  endTime: string
  status: string
  price?: number | null
  notes?: string | null
}

const mapProvider = (provider: ProviderResponse): Provider => ({
  id: provider.id,
  name: provider.name,
  type: provider.type,
  contact: provider.contact ?? null,
  notes: provider.notes ?? null,
  active: provider.active,
})

const mapServiceType = (type: ServiceTypeResponse): ServiceType => ({
  id: type.id,
  name: type.name,
  providerType: type.providerType,
  defaultDurationMinutes: type.defaultDurationMinutes,
  basePrice: type.basePrice,
  taxable: type.taxable,
})

const mapAppointment = (appointment: AppointmentResponse): Appointment => ({
  id: appointment.id,
  horse: appointment.horse,
  owner: appointment.owner,
  provider: appointment.serviceProvider ? mapProvider(appointment.serviceProvider) : null,
  serviceType: mapServiceType(appointment.serviceType),
  start: appointment.startTime,
  end: appointment.endTime,
  status: appointment.status,
  price: appointment.price ?? null,
  notes: appointment.notes ?? null,
})

export async function getProviders() {
  const res = await api.get<ProviderResponse[]>('/service-providers')
  return res.data.map(mapProvider)
}

export async function getServiceTypes() {
  const res = await api.get<ServiceTypeResponse[]>('/service-types')
  return res.data.map(mapServiceType)
}

export async function getAppointments() {
  const res = await api.get<AppointmentResponse[]>('/appointments')
  return res.data.map(mapAppointment)
}

export async function createAppointment(payload: CreateAppointmentPayload) {
  const body: Record<string, unknown> = {
    serviceTypeId: payload.serviceTypeId,
    startTime: payload.start,
    endTime: payload.end,
  }

  if (payload.providerId !== undefined) {
    body.serviceProviderId = payload.providerId
  }
  if (payload.horseId !== undefined) {
    body.horseId = payload.horseId
  }
  if (payload.notes !== undefined) {
    body.notes = payload.notes
  }
  if (payload.reminderOptIn !== undefined) {
    body.reminderOptIn = payload.reminderOptIn
  }

  const res = await api.post<AppointmentResponse>('/appointments', body)
  return mapAppointment(res.data)
}

export async function confirmAppointment(id: number) {
  const res = await api.post<AppointmentResponse>(`/appointments/${id}/confirm`)
  return mapAppointment(res.data)
}

export async function completeAppointment(id: number) {
  const res = await api.post<AppointmentResponse>(`/appointments/${id}/complete`)
  return mapAppointment(res.data)
}

export async function cancelAppointment(id: number) {
  const res = await api.post<AppointmentResponse>(`/appointments/${id}/cancel`)
  return mapAppointment(res.data)
}

