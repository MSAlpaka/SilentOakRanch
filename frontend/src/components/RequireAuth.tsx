import type { PropsWithChildren } from 'react'
import { Navigate } from 'react-router-dom'

import { useAuth } from '@/hooks/useAuth'

export default function RequireAuth({ children }: PropsWithChildren) {
  const { user, initialized, loading } = useAuth()

  if (!initialized || loading) {
    return null
  }

  return user ? <>{children}</> : <Navigate to="/login" replace />
}
