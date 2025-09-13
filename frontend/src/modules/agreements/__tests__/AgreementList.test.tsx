import { describe, it, expect, vi, afterEach, beforeEach } from 'vitest'
import { render, screen, fireEvent, cleanup, waitFor } from '@testing-library/react'
import { Provider } from 'react-redux'
import { createElement } from 'react'
import AgreementList from '../AgreementList'
import { loadAll } from '../agreementsSlice'
import * as agreementsApi from '../../../api/agreements'

vi.mock('react-i18next', () => ({
  useTranslation: () => ({ t: (s: string) => s }),
}))

vi.mock('../agreementsSlice', async actual => {
  const mod = await actual()
  return {
    ...mod,
    loadAll: vi.fn(() => ({ type: 'agreements/loadAll' })),
  }
})

vi.mock('../../../api/agreements', async actual => {
  const mod = await actual()
  return {
    ...mod,
    loadOne: vi.fn(() => Promise.resolve(new Blob(['test']))),
  }
})

describe('AgreementList', () => {
  beforeEach(() => {
    vi.clearAllMocks()
    // stub URL methods used in download
    globalThis.URL.createObjectURL = vi.fn(() => 'blob:')
    globalThis.URL.revokeObjectURL = vi.fn()
    ;(HTMLAnchorElement.prototype as any).click = vi.fn()
  })

  afterEach(() => {
    cleanup()
  })

  it('dispatches loadAll on mount and downloads agreements', async () => {
    const agreements = [
      { id: 1, type: 'TOS', version: '1.0', status: 'active' },
      { id: 2, type: 'Privacy', version: '2.0', status: 'active' },
    ]
    const store = {
      getState: () => ({ agreements: { items: agreements } }),
      dispatch: vi.fn(),
      subscribe: vi.fn(),
    }

    render(createElement(Provider, { store }, createElement(AgreementList)))

    expect(loadAll).toHaveBeenCalled()
    expect(store.dispatch).toHaveBeenCalledWith({ type: 'agreements/loadAll' })

    expect(screen.getByText('TOS')).toBeTruthy()
    expect(screen.getByText('Privacy')).toBeTruthy()

    const buttons = screen.getAllByRole('button', { name: 'agreements.download' })
    expect(buttons.length).toBe(2)

    fireEvent.click(buttons[0])

    await waitFor(() => {
      expect((agreementsApi.loadOne as any)).toHaveBeenCalledWith('1')
    })
  })
})

