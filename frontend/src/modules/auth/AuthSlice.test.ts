import { describe, it, expect, beforeEach } from 'vitest'
import reducer, { setToken, logout } from './authSlice'

describe('authSlice reducers', () => {
  beforeEach(() => {
    localStorage.clear()
  })

  it('setToken stores token and marks user as logged in', () => {
    const initial = { token: null, isLoggedIn: false }
    const state = reducer(initial, setToken('abc123'))
    expect(state.token).toBe('abc123')
    expect(state.isLoggedIn).toBe(true)
    expect(localStorage.getItem('token')).toBe('abc123')
  })

  it('logout clears token and marks user as logged out', () => {
    localStorage.setItem('token', 'abc123')
    const initial = { token: 'abc123', isLoggedIn: true }
    const state = reducer(initial, logout())
    expect(state.token).toBeNull()
    expect(state.isLoggedIn).toBe(false)
    expect(localStorage.getItem('token')).toBeNull()
  })
})
