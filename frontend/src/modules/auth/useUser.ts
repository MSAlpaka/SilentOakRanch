import { useEffect, useState } from 'react'
import api from '../../axios'
import { useAuth } from './AuthContext'
import { useTranslation } from 'react-i18next'

export type UserInfo = {
  id: number
  email: string
  roles: string[]
  firstName?: string
  lastName?: string
  assignedStallUnit?: {
    id: number
    label: string | null
  }
}

export function useUser() {
  const { token } = useAuth()
  const [user, setUser] = useState<UserInfo | null>(null)
  const [loading, setLoading] = useState(false)
  const [error, setError] = useState<string | null>(null)
  const { t } = useTranslation()

  useEffect(() => {
    if (!token) {
      setUser(null)
      setLoading(false)
      return
    }
    setLoading(true)
    setError(null)
    api
      .get('/me', { headers: { Authorization: `Bearer ${token}` } })
      .then(res => setUser(res.data as UserInfo))
      .catch(() => {
        setUser(null)
        setError(t('auth.user_error'))
      })
      .finally(() => setLoading(false))
  }, [token])

  return { user, loading, error }
}

export default useUser
