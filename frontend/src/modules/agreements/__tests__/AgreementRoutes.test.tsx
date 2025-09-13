import { describe, it, expect, vi } from 'vitest'
import { MemoryRouter, Routes, Route } from 'react-router-dom'
import { render, screen } from '@testing-library/react'
import { Provider } from 'react-redux'
import AgreementList from '../AgreementList'
import AdminAgreementUpload from '../AdminAgreementUpload'

vi.mock('react-i18next', () => ({
  useTranslation: () => ({ t: (s: string) => s }),
}))

describe('Agreement routes', () => {
  const agreements: any[] = []
  const store = {
    getState: () => ({ agreements: { items: agreements } }),
    dispatch: vi.fn(),
    subscribe: vi.fn(),
  }

  it('renders AgreementList for /agreements', () => {
    render(
      <Provider store={store}>
        <MemoryRouter initialEntries={['/agreements']} future={{ v7_startTransition: true, v7_relativeSplatPath: true }}>
          <Routes>
            <Route path="/agreements" element={<AgreementList />} />
          </Routes>
        </MemoryRouter>
      </Provider>
    )
    expect(screen.getByText('agreements.title')).toBeTruthy()
  })

  it('renders AdminAgreementUpload for /admin/agreements', () => {
    render(
      <Provider store={store}>
        <MemoryRouter initialEntries={['/admin/agreements']} future={{ v7_startTransition: true, v7_relativeSplatPath: true }}>
          <Routes>
            <Route path="/admin/agreements" element={<AdminAgreementUpload />} />
          </Routes>
        </MemoryRouter>
      </Provider>
    )
    expect(screen.getByText('agreements.upload')).toBeTruthy()
  })
})
