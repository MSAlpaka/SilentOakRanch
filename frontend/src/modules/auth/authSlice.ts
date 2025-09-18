import { createSlice, PayloadAction } from '@reduxjs/toolkit'

interface AuthState {
  isLoggedIn: boolean
}

const initialState: AuthState = {
  isLoggedIn: false,
}

const authSlice = createSlice({
  name: 'auth',
  initialState,
  reducers: {
    setAuthenticated(state, action: PayloadAction<boolean | undefined>) {
      state.isLoggedIn = action.payload ?? true
    },
    logout(state) {
      state.isLoggedIn = false
    },
  },
})

export const { setAuthenticated, logout } = authSlice.actions
export default authSlice.reducer
