import { describe, it, expect } from 'vitest'
import reducer, { setAuthenticated, logout } from './authSlice'

describe('authSlice reducers', () => {
  it('setAuthenticated marks user as logged in by default', () => {
    const initial = { isLoggedIn: false }
    const state = reducer(initial, setAuthenticated())
    expect(state.isLoggedIn).toBe(true)
  })

  it('setAuthenticated respects explicit payload', () => {
    const initial = { isLoggedIn: true }
    const state = reducer(initial, setAuthenticated(false))
    expect(state.isLoggedIn).toBe(false)
  })

  it('logout marks user as logged out', () => {
    const initial = { isLoggedIn: true }
    const state = reducer(initial, logout())
    expect(state.isLoggedIn).toBe(false)
  })
})
