import { ReactElement } from 'react'
import { Navigate } from 'react-router-dom'
import { useAuth } from './AuthContext'

export default function AuthGuard({ children, role }: { children: ReactElement; role?: 'admin' | 'staff' | 'customer' }) {
  const { token, role: currentRole } = useAuth()
  if (!token || (role && currentRole !== role)) {
    return <Navigate to="/login" replace />
  }
  return children
}
