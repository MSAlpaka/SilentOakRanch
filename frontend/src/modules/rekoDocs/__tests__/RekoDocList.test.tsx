import { describe, it, expect, vi, beforeEach, afterEach } from 'vitest'
import { render, screen, waitFor, cleanup } from '@testing-library/react'
import { createElement } from 'react'
import RekoDocList from '../RekoDocList'

const loadDocsMock = vi.hoisted(() => vi.fn())

vi.mock('../../../api/rekoDocs', () => ({
  loadDocs: loadDocsMock,
  exportDoc: vi.fn(),
}))

vi.mock('react-router-dom', () => ({
  useParams: () => ({ bookingId: '1' }),
}))

vi.mock('react-i18next', () => ({
  useTranslation: () => ({ t: (s: string) => s }),
}))

describe('RekoDocList', () => {
  beforeEach(() => {
    vi.clearAllMocks()
  })

  afterEach(() => {
    cleanup()
  })

  it('shows docs in chronological order and export button for premium', async () => {
    loadDocsMock.mockResolvedValue([
      {
        id: 1,
        type: 'basis',
        notes: 'second',
        createdAt: '2024-02-01T00:00:00Z',
      },
      {
        id: 2,
        type: 'premium',
        notes: 'first',
        createdAt: '2024-01-01T00:00:00Z',
      },
    ])

    render(createElement(RekoDocList))

    await waitFor(() => {
      expect(loadDocsMock).toHaveBeenCalled()
    })

    const items = screen.getAllByTestId('doc')
    expect(items[0].textContent).toContain('first')
    expect(items[1].textContent).toContain('second')

    const exportButtons = screen.getAllByText('rekoDocs.export')
    expect(exportButtons.length).toBe(1)
  })
})
