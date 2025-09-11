import { Routes, Route, Navigate } from 'react-router-dom'
import Login from './modules/auth/Login'
import Register from './pages/Register'
import InviteAccept from './pages/InviteAccept'
import Dashboard from './modules/dashboard/Dashboard'
import SubscriptionList from './modules/subscriptions/SubscriptionList'
import SubscriptionForm from './modules/subscriptions/SubscriptionForm'
import HorseList from './modules/horses/HorseList'
import HorseForm from './modules/horses/HorseForm'
import AuthGuard from './modules/auth/AuthGuard'

function App() {
  return (
    <Routes>
      <Route path="/login" element={<Login />} />
      <Route path="/register" element={<Register />} />
      <Route path="/accept-invite" element={<InviteAccept />} />
      <Route path="/dashboard" element={<AuthGuard><Dashboard /></AuthGuard>} />
      <Route path="/admin/subscriptions" element={<AuthGuard><SubscriptionList /></AuthGuard>} />
      <Route path="/admin/subscriptions/new" element={<AuthGuard><SubscriptionForm /></AuthGuard>} />
      <Route path="/admin/horses" element={<AuthGuard><HorseList /></AuthGuard>} />
      <Route path="/admin/horses/new" element={<AuthGuard><HorseForm /></AuthGuard>} />
      <Route path="*" element={<Navigate to="/dashboard" replace />} />
    </Routes>
  )
}

export default App
