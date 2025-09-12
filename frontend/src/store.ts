import { configureStore } from '@reduxjs/toolkit'
import { TypedUseSelectorHook, useDispatch, useSelector } from 'react-redux'
import authReducer from './modules/auth/authSlice'
import scaleBookingsReducer from './modules/scale/scaleBookingSlice'
import invoicesReducer from './modules/invoices/invoiceSlice'
import agreementsReducer from './modules/agreements/agreementsSlice'

export const store = configureStore({
  reducer: {
    auth: authReducer,
    scaleBookings: scaleBookingsReducer,
    invoices: invoicesReducer,
    agreements: agreementsReducer,
  },
})

export type RootState = ReturnType<typeof store.getState>
export type AppDispatch = typeof store.dispatch

export const useAppDispatch: () => AppDispatch = useDispatch
export const useAppSelector: TypedUseSelectorHook<RootState> = useSelector
