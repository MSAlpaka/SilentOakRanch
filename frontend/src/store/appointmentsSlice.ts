import { createAsyncThunk, createSlice } from '@reduxjs/toolkit'
import type { RootState } from '../store'
import * as api from '../api/appointments'

interface AppointmentsState {
  providers: api.Provider[]
  serviceTypes: api.ServiceType[]
  appointments: api.Appointment[]
  loading: boolean
  error?: string
}

const initialState: AppointmentsState = {
  providers: [],
  serviceTypes: [],
  appointments: [],
  loading: false,
}

export const loadProviders = createAsyncThunk('appointments/loadProviders', async () => {
  return api.getProviders()
})

export const loadServiceTypes = createAsyncThunk('appointments/loadServiceTypes', async () => {
  return api.getServiceTypes()
})

export const loadAppointments = createAsyncThunk('appointments/loadAppointments', async () => {
  return api.getAppointments()
})

export const create = createAsyncThunk(
  'appointments/create',
  async (payload: api.CreateAppointmentPayload) => {
    return api.createAppointment(payload)
  }
)

export const confirm = createAsyncThunk('appointments/confirm', async (id: number) => {
  return api.confirmAppointment(id)
})

export const complete = createAsyncThunk('appointments/complete', async (id: number) => {
  return api.completeAppointment(id)
})

export const cancel = createAsyncThunk('appointments/cancel', async (id: number) => {
  return api.cancelAppointment(id)
})

const appointmentsSlice = createSlice({
  name: 'appointments',
  initialState,
  reducers: {},
  extraReducers: builder => {
    builder
      .addCase(loadProviders.fulfilled, (state, action) => {
        state.providers = action.payload
      })
      .addCase(loadServiceTypes.fulfilled, (state, action) => {
        state.serviceTypes = action.payload
      })
      .addCase(loadAppointments.fulfilled, (state, action) => {
        state.appointments = action.payload
      })
      .addCase(create.fulfilled, (state, action) => {
        state.appointments.push(action.payload)
      })
      .addCase(confirm.fulfilled, (state, action) => {
        const idx = state.appointments.findIndex(a => a.id === action.payload.id)
        if (idx !== -1) state.appointments[idx] = action.payload
      })
      .addCase(complete.fulfilled, (state, action) => {
        const idx = state.appointments.findIndex(a => a.id === action.payload.id)
        if (idx !== -1) state.appointments[idx] = action.payload
      })
      .addCase(cancel.fulfilled, (state, action) => {
        const idx = state.appointments.findIndex(a => a.id === action.payload.id)
        if (idx !== -1) state.appointments[idx] = action.payload
      })
      .addMatcher(action => action.type.startsWith('appointments/') && action.type.endsWith('/pending'), state => {
        state.loading = true
        state.error = undefined
      })
      .addMatcher(action => action.type.startsWith('appointments/') && action.type.endsWith('/rejected'), (state, action) => {
        state.loading = false
        state.error = action.error.message
      })
      .addMatcher(action => action.type.startsWith('appointments/') && action.type.endsWith('/fulfilled'), state => {
        state.loading = false
      })
  },
})

export const selectAppointments = (state: RootState) => state.appointments.appointments
export const selectProviders = (state: RootState) => state.appointments.providers
export const selectServiceTypes = (state: RootState) => state.appointments.serviceTypes

export default appointmentsSlice.reducer

