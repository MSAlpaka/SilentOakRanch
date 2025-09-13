import { describe, it, expect, vi, afterEach } from 'vitest'
import { render, screen, fireEvent, waitFor, cleanup } from '@testing-library/react'
import { createElement } from 'react'
import AdminAgreementUpload from '../AdminAgreementUpload'
import * as agreementsApi from '../../../api/agreements'

vi.mock('react-i18next', () => ({
  useTranslation: () => ({ t: (s: string) => s }),
}))

vi.mock('../../../api/agreements', async actual => {
  const mod = await actual()
  return {
    ...mod,
    uploadAgreement: vi.fn(() => Promise.resolve({ id: 1 })),
  }
})

describe('AdminAgreementUpload', () => {
  afterEach(() => {
    cleanup()
    vi.clearAllMocks()
  })

  it('uploads agreement on submit', async () => {
    render(createElement(AdminAgreementUpload))

    const typeSelect = screen.getByLabelText('agreements.type')
    fireEvent.change(typeSelect, { target: { value: 'Privacy' } })

    const versionInput = screen.getByLabelText('agreements.version')
    fireEvent.change(versionInput, { target: { value: '1.0' } })

    const fileInput = screen.getByLabelText('agreements.file')
    const file = new File(['test'], 'test.pdf', { type: 'application/pdf' })
    fireEvent.change(fileInput, { target: { files: [file] } })

    fireEvent.click(screen.getByText('agreements.upload'))

    await waitFor(() => {
      expect(agreementsApi.uploadAgreement).toHaveBeenCalledWith({
        type: 'Privacy',
        version: '1.0',
        file,
      })
    })
  })

  it('does not submit without file', async () => {
    render(createElement(AdminAgreementUpload))
    fireEvent.click(screen.getByText('agreements.upload'))

    await waitFor(() => {
      expect(agreementsApi.uploadAgreement).not.toHaveBeenCalled()
    })
  })
})
