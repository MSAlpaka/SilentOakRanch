import api from '../axios'

export type RekoDocType = 'basis' | 'standard' | 'premium'

export interface RekoDoc {
  id: number
  type: RekoDocType
  notes?: string
  photos?: string[]
  videos?: string[]
  metrics?: Record<string, unknown>
  createdAt: string
}

export type LoadDocsResponse = RekoDoc[]

export interface CreateDocPayload {
  type: RekoDocType
  notes: string
  photos?: string[]
  videos?: string[]
  metrics?: Record<string, unknown>
}

export type CreateDocResponse = RekoDoc

export type ExportDocResponse = Blob

export async function loadDocs(bookingId: string): Promise<LoadDocsResponse> {
  const response = await api.get<LoadDocsResponse>(`/reko/${bookingId}/docs`)
  return response.data
}

export async function createDoc(bookingId: string, payload: CreateDocPayload): Promise<CreateDocResponse> {
  const response = await api.post<CreateDocResponse>(`/reko/${bookingId}/docs/new`, payload)
  return response.data
}

export async function exportDoc(docId: string): Promise<ExportDocResponse> {
  const response = await api.get<ExportDocResponse>(`/reko/docs/${docId}/export`, { responseType: 'blob' })
  return response.data
}

