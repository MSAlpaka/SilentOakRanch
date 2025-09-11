import { Navigate, Outlet } from 'react-router-dom'
import { useAuth } from './AuthContext'

interface PrivateRouteProps {
  roles?: Array<'admin' | 'staff' | 'customer'>
}

export default function PrivateRoute({ roles }: PrivateRouteProps) {
  const { token, role } = useAuth()

  if (!token) {
    return <Navigate to="/login" replace />
  }

  if (roles && (!role || !roles.includes(role))) {
    return <Navigate to="/login" replace />
  }

  return <Outlet />
}
