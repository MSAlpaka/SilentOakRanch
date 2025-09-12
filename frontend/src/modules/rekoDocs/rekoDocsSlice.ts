import { createAsyncThunk, createSlice } from '@reduxjs/toolkit'
import api from '../../axios'

export interface RekoDoc {
  id: number
  type: string
  notes?: string
  photos?: string[]
  videos?: string[]
  metrics?: Record<string, unknown>
  createdAt: string
}

interface RekoDocsState {
  items: RekoDoc[]
  loading: boolean
  error?: string
}

const initialState: RekoDocsState = {
  items: [],
  loading: false,
}

export const loadDocs = createAsyncThunk('rekoDocs/loadDocs', async (bookingId: number) => {
  const response = await api.get<RekoDoc[]>(`/reko/${bookingId}/docs`)
  return response.data
})

export const createDoc = createAsyncThunk(
  'rekoDocs/createDoc',
  async ({ bookingId, doc }: { bookingId: number; doc: any }) => {
    const response = await api.post<RekoDoc>(`/reko/${bookingId}/docs/new`, doc)
    return response.data
  }
)

export const exportDoc = createAsyncThunk('rekoDocs/exportDoc', async (id: number) => {
  const response = await api.get(`/reko/docs/${id}/export`, { responseType: 'blob' })
  return response.data
})

const rekoDocsSlice = createSlice({
  name: 'rekoDocs',
  initialState,
  reducers: {},
  extraReducers: builder => {
    builder.addCase(loadDocs.fulfilled, (state, action) => {
      state.items = [...action.payload].sort((a, b) => a.createdAt.localeCompare(b.createdAt))
    })
    builder.addCase(createDoc.fulfilled, (state, action) => {
      state.items.push(action.payload)
      state.items.sort((a, b) => a.createdAt.localeCompare(b.createdAt))
    })
    builder
      .addMatcher(
        action => action.type.startsWith('rekoDocs/') && action.type.endsWith('/pending'),
        state => {
          state.loading = true
          state.error = undefined
        }
      )
      .addMatcher(
        action => action.type.startsWith('rekoDocs/') && action.type.endsWith('/fulfilled'),
        state => {
          state.loading = false
        }
      )
      .addMatcher(
        action => action.type.startsWith('rekoDocs/') && action.type.endsWith('/rejected'),
        (state, action) => {
          state.loading = false
          state.error = action.error.message
        }
      )
  },
})

export default rekoDocsSlice.reducer
