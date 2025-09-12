import { describe, it, expect, vi, beforeEach, afterEach } from 'vitest'
import { render, screen, fireEvent, waitFor, cleanup } from '@testing-library/react'
import { createElement } from 'react'
import RekoDocForm from '../RekoDocForm'

const createDocMock = vi.hoisted(() => vi.fn())
const navigateMock = vi.hoisted(() => vi.fn())

vi.mock('../../../api/rekoDocs', () => ({
  createDoc: createDocMock,
}))

vi.mock('react-router-dom', () => ({
  useNavigate: () => navigateMock,
  useParams: () => ({ bookingId: '1' }),
}))

vi.mock('react-i18next', () => ({
  useTranslation: () => ({ t: (s: string) => s }),
}))

describe('RekoDocForm', () => {
  beforeEach(() => {
    vi.clearAllMocks()
    createDocMock.mockResolvedValue({})
  })

  afterEach(() => {
    cleanup()
  })

  it('validates videos not allowed for standard docs', async () => {
    render(createElement(RekoDocForm))

    const typeSelect = screen.getByRole('combobox')
    fireEvent.change(typeSelect, { target: { value: 'premium' } })

    const file = new File(['data'], 'video.mp4', { type: 'video/mp4' })
    const videoInput = screen.getByTestId('video-input')
    fireEvent.change(videoInput, { target: { files: [file] } })

    fireEvent.change(typeSelect, { target: { value: 'standard' } })

    fireEvent.click(screen.getByText('rekoDocs.save'))

    await waitFor(() => {
      expect(screen.getByText('rekoDocs.no_videos_standard')).toBeTruthy()
    })

    expect(createDocMock).not.toHaveBeenCalled()
  })
})
