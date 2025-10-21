import { Navigate, Outlet } from 'react-router-dom'
import { useAuth } from '@/hooks/useAuth'

interface PrivateRouteProps {
  roles?: Array<'admin' | 'staff' | 'customer'>
}

export default function PrivateRoute({ roles }: PrivateRouteProps) {
  const { isAuthenticated, role, isLoading } = useAuth()

  if (isLoading) {
    return null
  }

  if (!isAuthenticated) {
    return <Navigate to="/login" replace />
  }

  if (roles && (!role || !roles.includes(role))) {
    return <Navigate to="/login" replace />
  }

  return <Outlet />
}
