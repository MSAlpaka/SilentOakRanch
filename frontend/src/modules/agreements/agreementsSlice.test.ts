import { describe, it, expect } from 'vitest'
import reducer, {
  loadAll,
  giveConsent,
} from './agreementsSlice'

describe('agreementsSlice', () => {
  it('handles loadAll.fulfilled', () => {
    const state = reducer(undefined, loadAll.fulfilled([{ id: 1 }], '', undefined) as any)
    expect(state.items).toEqual([{ id: 1 }])
  })

  it('handles giveConsent.fulfilled', () => {
    const initial = { items: [], loading: false } as any
    const state = reducer(initial, giveConsent.fulfilled({ id: 2 }, '', 'TOS') as any)
    expect(state.items).toEqual([{ id: 2 }])
  })

  it('sets loading true on pending and clears error', () => {
    const initial = { items: [], loading: false, error: 'oops' } as any
    const state = reducer(initial, loadAll.pending('', undefined))
    expect(state.loading).toBe(true)
    expect(state.error).toBeUndefined()
  })

  it('sets error on rejected', () => {
    const initial = { items: [], loading: true } as any
    const action = loadAll.rejected(new Error('fail'), '', undefined)
    const state = reducer(initial, action)
    expect(state.loading).toBe(false)
    expect(state.error).toBe('fail')
  })
})

