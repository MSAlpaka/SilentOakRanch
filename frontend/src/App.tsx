import { Routes, Route, Navigate } from 'react-router-dom'
import Login from './modules/auth/Login'
import Register from './pages/Register'
import InviteAccept from './pages/InviteAccept'
import Dashboard from './modules/dashboard/Dashboard'
import SubscriptionList from './modules/subscriptions/SubscriptionList'
import SubscriptionForm from './modules/subscriptions/SubscriptionForm'
import HorseList from './modules/horses/HorseList'
import HorseForm from './modules/horses/HorseForm'
import BookingList from './modules/bookings/BookingList'
import BookingForm from './modules/bookings/BookingForm'
import PrivateRoute from './modules/auth/PrivateRoute'

function App() {
  return (
    <Routes>
      <Route path="/login" element={<Login />} />
      <Route path="/register" element={<Register />} />
      <Route path="/accept-invite" element={<InviteAccept />} />
      <Route element={<PrivateRoute />}>
        <Route path="/dashboard" element={<Dashboard />} />
        <Route path="/bookings" element={<BookingList />} />
        <Route path="/bookings/new" element={<BookingForm />} />
      </Route>
      <Route element={<PrivateRoute roles={['admin', 'staff']} />}>
        <Route path="/admin/subscriptions" element={<SubscriptionList />} />
        <Route path="/admin/subscriptions/new" element={<SubscriptionForm />} />
        <Route path="/admin/horses" element={<HorseList />} />
        <Route path="/admin/horses/new" element={<HorseForm />} />
      </Route>
      <Route path="*" element={<Navigate to="/dashboard" replace />} />
    </Routes>
  )
}

export default App
