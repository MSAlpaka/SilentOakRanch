import { useState } from 'react'
import { useNavigate } from 'react-router-dom'
import { register } from '../api/auth'
import { useTranslation } from 'react-i18next'

function Register() {
  const navigate = useNavigate()
  const [username, setUsername] = useState('')
  const [password, setPassword] = useState('')
  const [error, setError] = useState<string | null>(null)
  const { t } = useTranslation()

  async function handleSubmit(e: React.FormEvent) {
    e.preventDefault()
    try {
      await register(username, password)
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
          placeholder={t('auth.register.username')}
          value={username}
          onChange={e => setUsername(e.target.value)}
        />
        <input
          className="border w-full p-2 mb-4"
          type="password"
          placeholder={t('auth.register.password')}
          value={password}
          onChange={e => setPassword(e.target.value)}
        />
        <button className="bg-blue-500 text-white px-4 py-2 w-full">{t('auth.register.button')}</button>
      </form>
    </div>
  )
}

export default Register
