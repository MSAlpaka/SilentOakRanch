import { Navigate } from 'react-router-dom'
import type { PropsWithChildren } from 'react'

import { useAuth } from '@/context/AuthContext'

export default function RequireAuth({ children }: PropsWithChildren) {
  const { user, initialized, isLoading } = useAuth()

  if (!initialized || isLoading) {
    return null
  }

  return user ? <>{children}</> : <Navigate to="/login" replace />
}
