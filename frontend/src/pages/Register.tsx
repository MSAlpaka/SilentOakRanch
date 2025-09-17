import { useState } from 'react'
import { useNavigate } from 'react-router-dom'
import { register } from '../api/auth'
import * as agreementsApi from '../api/agreements'
import { useTranslation } from 'react-i18next'

function Register() {
  const navigate = useNavigate()
  const [firstName, setFirstName] = useState('')
  const [lastName, setLastName] = useState('')
  const [email, setEmail] = useState('')
  const [password, setPassword] = useState('')
  const [agb, setAgb] = useState(false)
  const [privacy, setPrivacy] = useState(false)
  const [agbError, setAgbError] = useState(false)
  const [privacyError, setPrivacyError] = useState(false)
  const [error, setError] = useState<string | null>(null)
  const { t } = useTranslation()

  async function handleSubmit(e: React.FormEvent) {
    e.preventDefault()
    let valid = true
    if (!firstName || !lastName || !email || !password) {
      setError(t('auth.register.missing_fields'))
      valid = false
    } else {
      setError(null)
    }
    if (!agb) {
      setAgbError(true)
      valid = false
    }
    if (!privacy) {
      setPrivacyError(true)
      valid = false
    }
    if (!valid) {
      return
    }
    try {
      await register({
        firstName,
        lastName,
        email,
        password,
        role: 'customer',
      })
      await agreementsApi.giveConsent('agb')
      await agreementsApi.giveConsent('privacy')
      navigate('/login')
    } catch (err) {
      setError(t('auth.register.error'))
    }
  }

  return (
    <div className="flex items-center justify-center h-screen bg-gray-200">
      <form onSubmit={handleSubmit} className="bg-white p-6 rounded shadow w-80">
        <h1 className="text-2xl mb-4">{t('auth.register.title')}</h1>
        {error && <p className="text-red-500 mb-2">{error}</p>}
        <input
          className="border w-full p-2 mb-2"
          type="text"
          placeholder={t('auth.register.first_name')}
          value={firstName}
          onChange={e => setFirstName(e.target.value)}
        />
        <input
          className="border w-full p-2 mb-2"
          type="text"
          placeholder={t('auth.register.last_name')}
          value={lastName}
          onChange={e => setLastName(e.target.value)}
        />
        <input
          className="border w-full p-2 mb-2"
          type="email"
          placeholder={t('auth.register.email')}
          value={email}
          onChange={e => setEmail(e.target.value)}
        />
        <input
          className="border w-full p-2 mb-2"
          type="password"
          placeholder={t('auth.register.password')}
          value={password}
          onChange={e => setPassword(e.target.value)}
        />
        <div className="mb-2">
          <input
            id="terms"
            type="checkbox"
            checked={agb}
            onChange={e => {
              setAgb(e.target.checked)
              if (e.target.checked) {
                setAgbError(false)
              }
            }}
          />
          <label htmlFor="terms" className="ml-2">
            {t('auth.register.terms')}
          </label>
          {agbError && (
            <p className="text-red-500 text-sm">{t('auth.register.terms_required')}</p>
          )}
        </div>
        <div className="mb-4">
          <input
            id="privacy"
            type="checkbox"
            checked={privacy}
            onChange={e => {
              setPrivacy(e.target.checked)
              if (e.target.checked) {
                setPrivacyError(false)
              }
            }}
          />
          <label htmlFor="privacy" className="ml-2">
            {t('auth.register.privacy')}
          </label>
          {privacyError && (
            <p className="text-red-500 text-sm">{t('auth.register.privacy_required')}</p>
          )}
        </div>
        <button className="bg-blue-500 text-white px-4 py-2 w-full">
          {t('auth.register.button')}
        </button>
      </form>
    </div>
  )
}

export default Register
