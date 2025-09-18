import { createAsyncThunk, createSlice } from '@reduxjs/toolkit'
import api from '../../axios'

export interface Invoice {
  id: number
  createdAt: string
  amount: string
  status: string
  downloadUrl: string
}

interface InvoiceState {
  items: Invoice[]
  current?: Invoice
}

const initialState: InvoiceState = {
  items: [],
}

export const fetchAll = createAsyncThunk('invoices/fetchAll', async () => {
  const response = await api.get<Invoice[]>('/invoices')
  return response.data
})

export const fetchById = createAsyncThunk('invoices/fetchById', async (id: number) => {
  const response = await api.get<Invoice>(`/invoices/${id}/meta`)
  return response.data
})

const invoiceSlice = createSlice({
  name: 'invoices',
  initialState,
  reducers: {},
  extraReducers: builder => {
    builder.addCase(fetchAll.fulfilled, (state, action) => {
      state.items = action.payload
    })
    builder.addCase(fetchById.fulfilled, (state, action) => {
      state.current = action.payload
    })
  },
})

export default invoiceSlice.reducer
