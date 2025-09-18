import { useState } from 'react'
import { useNavigate } from 'react-router-dom'
import { login as loginRequest } from '../api/auth'
import { useAppDispatch } from '../store'
import { setAuthenticated } from '../modules/auth/authSlice'
import { useTranslation } from 'react-i18next'
import { useAuth } from '../modules/auth/AuthContext'

function Login() {
  const dispatch = useAppDispatch()
  const { refresh } = useAuth()
  const navigate = useNavigate()
  const [email, setEmail] = useState('')
  const [password, setPassword] = useState('')
  const [error, setError] = useState<string | null>(null)
  const { t } = useTranslation()

  async function handleSubmit(e: React.FormEvent) {
    e.preventDefault()
    try {
      const data = await loginRequest(email, password)
      await refresh({ role: data.role, roles: data.roles })
      dispatch(setAuthenticated(true))
      navigate('/dashboard')
    } catch (err) {
      setError(t('auth.login.error'))
    }
  }

  return (
    <div className="flex items-center justify-center h-screen bg-gray-200">
      <form onSubmit={handleSubmit} className="bg-white p-6 rounded shadow w-80">
        <h1 className="text-2xl mb-4">{t('auth.login.title')}</h1>
        {error && <p className="text-red-500 mb-2">{error}</p>}
        <input
          className="border w-full p-2 mb-2"
          type="email"
          placeholder={t('auth.login.email')}
          value={email}
          onChange={e => setEmail(e.target.value)}
        />
        <input
          className="border w-full p-2 mb-4"
          type="password"
          placeholder={t('auth.login.password')}
          value={password}
          onChange={e => setPassword(e.target.value)}
        />
        <button className="bg-blue-500 text-white px-4 py-2 w-full">{t('auth.login.button')}</button>
      </form>
    </div>
  )
}

export default Login
