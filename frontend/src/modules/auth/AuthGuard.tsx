import { ReactElement } from 'react'
import { Navigate } from 'react-router-dom'
import { useAuth } from '@/hooks/useAuth'

export default function AuthGuard({ children, role }: { children: ReactElement; role?: 'admin' | 'staff' | 'customer' }) {
  const { isAuthenticated, role: currentRole, isLoading } = useAuth()
  if (isLoading) {
    return null
  }
  if (!isAuthenticated || (role && currentRole !== role)) {
    return <Navigate to="/login" replace />
  }
  return children
}
