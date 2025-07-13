import { Routes, Route, Navigate } from 'react-router-dom'
import Login from './modules/auth/Login'
import Dashboard from './modules/dashboard/Dashboard'
import { useAuth } from './modules/auth/AuthContext'
import AuthGuard from './modules/auth/AuthGuard'
import SubscriptionList from './modules/subscriptions/SubscriptionList'
import SubscriptionForm from './modules/subscriptions/SubscriptionForm'

function App() {
  const { token } = useAuth()

  return (
    <Routes>
      <Route path="/login" element={<Login />} />
      <Route
        path="/dashboard"
        element={token ? <Dashboard /> : <Navigate to="/login" replace />}
      />
      <Route
        path="/admin/subscriptions"
        element={
          <AuthGuard role="admin">
            <SubscriptionList />
          </AuthGuard>
        }
      />
      <Route
        path="/admin/subscriptions/new"
        element={
          <AuthGuard role="admin">
            <SubscriptionForm />
          </AuthGuard>
        }
      />
      <Route path="*" element={<Navigate to="/dashboard" replace />} />
    </Routes>
  )
}

export default App
