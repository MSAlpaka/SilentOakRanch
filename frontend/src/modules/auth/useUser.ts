import { useEffect, useState } from 'react'
import { useTranslation } from 'react-i18next'
import { useAuth } from './AuthContext'

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
  const { user, isLoading, error: authError } = useAuth()
  const [error, setError] = useState<string | null>(null)
  const { t } = useTranslation()

  useEffect(() => {
    if (!authError) {
      setError(null)
      return
    }

    setError(t('auth.user_error'))
  }, [authError, t])

  return { user, loading: isLoading, error }
}

export default useUser
