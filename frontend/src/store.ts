import { configureStore } from '@reduxjs/toolkit'
import { TypedUseSelectorHook, useDispatch, useSelector } from 'react-redux'
import authReducer from './modules/auth/authSlice'
import scaleBookingsReducer from './modules/scale/scaleBookingSlice'

export const store = configureStore({
  reducer: {
    auth: authReducer,
    scaleBookings: scaleBookingsReducer,
  },
})

export type RootState = ReturnType<typeof store.getState>
export type AppDispatch = typeof store.dispatch

export const useAppDispatch: () => AppDispatch = useDispatch
export const useAppSelector: TypedUseSelectorHook<RootState> = useSelector
