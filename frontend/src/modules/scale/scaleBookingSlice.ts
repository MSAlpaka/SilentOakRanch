import { createAsyncThunk, createSlice } from '@reduxjs/toolkit'
import {
  getSlots,
  create as createBookingApi,
  confirm as confirmBookingApi,
  setWeight as setWeightApi,
  CreateScaleBookingPayload,
} from '../../api/scaleBookings'

export interface ScaleBooking {
  id: string
  status: string
  price?: number
  weight?: number
}

interface ScaleBookingState {
  slots: string[]
  bookings: Record<string, ScaleBooking>
  loading: boolean
  error?: string
}

const initialState: ScaleBookingState = {
  slots: [],
  bookings: {},
  loading: false,
}

export const fetchScaleSlots = createAsyncThunk(
  'scaleBookings/fetchSlots',
  async (day?: string) => {
    return await getSlots(day)
  }
)

export const createScaleBooking = createAsyncThunk(
  'scaleBookings/create',
  async (payload: CreateScaleBookingPayload) => {
    return await createBookingApi(payload)
  }
)

export const confirmScaleBooking = createAsyncThunk(
  'scaleBookings/confirm',
  async (id: string) => {
    return await confirmBookingApi(id)
  }
)

export const setScaleBookingWeight = createAsyncThunk(
  'scaleBookings/setWeight',
  async ({ id, weight }: { id: string; weight: number }) => {
    return await setWeightApi(id, weight)
  }
)

const scaleBookingSlice = createSlice({
  name: 'scaleBookings',
  initialState,
  reducers: {},
  extraReducers: builder => {
    builder.addCase(fetchScaleSlots.fulfilled, (state, action) => {
      state.slots = action.payload
    })
    builder.addCase(createScaleBooking.fulfilled, (state, action) => {
      const booking = action.payload as ScaleBooking
      state.bookings[booking.id] = booking
    })
    builder.addCase(confirmScaleBooking.fulfilled, (state, action) => {
      const id = action.meta.arg
      if (state.bookings[id]) {
        state.bookings[id].status = action.payload.status
      }
    })
    builder.addCase(setScaleBookingWeight.fulfilled, (state, action) => {
      const { id } = action.meta.arg
      if (state.bookings[id]) {
        state.bookings[id].status = action.payload.status
        state.bookings[id].weight = action.payload.weight
      }
    })
    builder
      .addMatcher(
        action => action.type.startsWith('scaleBookings/') && action.type.endsWith('/pending'),
        state => {
          state.loading = true
          state.error = undefined
        }
      )
      .addMatcher(
        action => action.type.startsWith('scaleBookings/') && action.type.endsWith('/fulfilled'),
        state => {
          state.loading = false
        }
      )
      .addMatcher(
        action => action.type.startsWith('scaleBookings/') && action.type.endsWith('/rejected'),
        (state, action) => {
          state.loading = false
          state.error = action.error.message
        }
      )
  },
})

export default scaleBookingSlice.reducer
