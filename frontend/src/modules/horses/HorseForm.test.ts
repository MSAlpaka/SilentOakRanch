import { describe, it, expect, vi, beforeEach, afterEach } from 'vitest'
import { render, screen, fireEvent, waitFor, cleanup } from '@testing-library/react'
import { createElement } from 'react'
import HorseForm from './HorseForm'

const navigateMock = vi.hoisted(() => vi.fn())
const postMock = vi.hoisted(() => vi.fn())

vi.mock('../../axios', () => ({
  default: { post: postMock },
}))

vi.mock('react-router-dom', () => ({
  useNavigate: () => navigateMock,
}))

vi.mock('react-i18next', () => ({
  useTranslation: () => ({ t: (s: string) => s }),
}))

describe('HorseForm', () => {
  beforeEach(() => {
    vi.clearAllMocks()
    postMock.mockResolvedValue({})
  })

  afterEach(() => {
    cleanup()
  })

  it('submits form data to API', async () => {
    render(createElement(HorseForm))
    const [nameInput, breedInput] = screen.getAllByRole('textbox')
    const ageInput = screen.getByRole('spinbutton')

    fireEvent.input(nameInput, { target: { value: 'Star' } })
    fireEvent.input(ageInput, { target: { value: '5' } })
    fireEvent.input(breedInput, { target: { value: 'Arabian' } })

    fireEvent.click(screen.getByText('horses.save'))

    await waitFor(() => {
      expect(postMock).toHaveBeenCalledWith('/horses', {
        name: 'Star',
        age: 5,
        breed: 'Arabian',
      })
      expect(navigateMock).toHaveBeenCalledWith('/admin/horses')
    })
  })
})
