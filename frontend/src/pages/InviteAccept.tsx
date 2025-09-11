import { useState } from 'react'
import { useNavigate, useSearchParams } from 'react-router-dom'
import { acceptInvite } from '../api/auth'
import { useAppDispatch } from '../store'
import { setToken } from '../modules/auth/authSlice'
import { useTranslation } from 'react-i18next'

function InviteAccept() {
  const [searchParams] = useSearchParams()
  const inviteToken = searchParams.get('token') || ''
  const dispatch = useAppDispatch()
  const navigate = useNavigate()
  const [password, setPassword] = useState('')
  const [error, setError] = useState<string | null>(null)
  const { t } = useTranslation()

  async function handleSubmit(e: React.FormEvent) {
    e.preventDefault()
    try {
      const data = await acceptInvite(inviteToken, password)
      dispatch(setToken(data.token))
      navigate('/dashboard')
    } catch (err) {
      setError(t('auth.invite.error'))
    }
  }

  return (
    <div className="flex items-center justify-center h-screen bg-gray-200">
      <form onSubmit={handleSubmit} className="bg-white p-6 rounded shadow w-80">
        <h1 className="text-2xl mb-4">{t('auth.invite.title')}</h1>
        {error && <p className="text-red-500 mb-2">{error}</p>}
        <input
          className="border w-full p-2 mb-4"
          type="password"
          placeholder={t('auth.invite.new_password')}
          value={password}
          onChange={e => setPassword(e.target.value)}
        />
        <button className="bg-blue-500 text-white px-4 py-2 w-full">{t('auth.invite.button')}</button>
      </form>
    </div>
  )
}

export default InviteAccept
