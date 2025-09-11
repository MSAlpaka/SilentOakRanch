import { Routes, Route, Navigate } from 'react-router-dom'
import Login from './pages/Login'
import Register from './pages/Register'
import InviteAccept from './pages/InviteAccept'
import Dashboard from './modules/dashboard/Dashboard'
import SubscriptionList from './modules/subscriptions/SubscriptionList'
import SubscriptionForm from './modules/subscriptions/SubscriptionForm'
import { useAppSelector } from './store'

function App() {
  const token = useAppSelector(state => state.auth.token)

  return (
    <Routes>
      <Route path="/login" element={<Login />} />
      <Route path="/register" element={<Register />} />
      <Route path="/accept-invite" element={<InviteAccept />} />
      <Route
        path="/dashboard"
        element={token ? <Dashboard /> : <Navigate to="/login" replace />}
      />
      <Route
        path="/admin/subscriptions"
        element={token ? <SubscriptionList /> : <Navigate to="/login" replace />}
      />
      <Route
        path="/admin/subscriptions/new"
        element={token ? <SubscriptionForm /> : <Navigate to="/login" replace />}
      />
      <Route path="*" element={<Navigate to="/dashboard" replace />} />
    </Routes>
  )
}

export default App
