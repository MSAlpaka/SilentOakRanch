import { describe, it, expect } from 'vitest'
import reducer, { loadDocs, createDoc } from './rekoDocsSlice'

describe('rekoDocsSlice', () => {
  it('handles loadDocs.fulfilled and sorts by createdAt', () => {
    const docs = [
      { id: 1, createdAt: '2023-02-01' },
      { id: 2, createdAt: '2023-01-01' },
    ] as any
    const state = reducer(undefined, loadDocs.fulfilled(docs, '', 1) as any)
    expect(state.items.map((d: any) => d.id)).toEqual([2, 1])
  })

  it('handles createDoc.fulfilled and maintains sorting', () => {
    const initial = {
      items: [{ id: 1, createdAt: '2023-02-01' }],
      loading: false,
    } as any
    const newDoc = { id: 2, createdAt: '2023-01-01' }
    const state = reducer(initial, createDoc.fulfilled(newDoc, '', { bookingId: 1, doc: {} }) as any)
    expect(state.items.map((d: any) => d.id)).toEqual([2, 1])
  })

  it('sets loading true on pending and clears error', () => {
    const initial = { items: [], loading: false, error: 'oops' } as any
    const state = reducer(initial, loadDocs.pending('', 1))
    expect(state.loading).toBe(true)
    expect(state.error).toBeUndefined()
  })

  it('sets error on rejected', () => {
    const initial = { items: [], loading: true } as any
    const action = loadDocs.rejected(new Error('fail'), '', 1)
    const state = reducer(initial, action)
    expect(state.loading).toBe(false)
    expect(state.error).toBe('fail')
  })
})
