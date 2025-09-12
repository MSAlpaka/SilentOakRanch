import api from '../axios'

export interface Agreement {
  id: number
  type: string
  version: string
  status: string
  filePath?: string
}

export interface UploadAgreementPayload {
  type: string
  version: string
  file: File
}

export async function loadAll() {
  const response = await api.get<Agreement[]>('/agreements')
  return response.data
}

export async function loadOne(id: string) {
  const response = await api.get(`/agreements/${id}`, { responseType: 'blob' })
  return response.data
}

export async function giveConsent(type: string) {
  const response = await api.post('/agreements/consent', { type })
  return response.data
}

export async function uploadAgreement(payload: UploadAgreementPayload) {
  const form = new FormData()
  form.append('type', payload.type)
  form.append('version', payload.version)
  form.append('file', payload.file)
  const response = await api.post('/agreements/upload', form)
  return response.data
}

