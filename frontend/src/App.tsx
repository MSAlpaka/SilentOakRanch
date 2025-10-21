import { Routes, Route, Navigate } from 'react-router-dom'
import { Toaster } from 'sonner'

import RequireAuth from '@/components/RequireAuth'
import DashboardLayout from '@/layouts/DashboardLayout'
import DashboardSettings from '@/pages/DashboardSettings'
import BookingDashboard from '@/modules/booking/BookingDashboard'
import Login from '@/pages/Login'
import PrivateRoute from '@/modules/auth/PrivateRoute'
import Dashboard from '@/modules/dashboard/Dashboard'
import SubscriptionList from '@/modules/subscriptions/SubscriptionList'
import SubscriptionForm from '@/modules/subscriptions/SubscriptionForm'
import HorseList from '@/modules/horses/HorseList'
import HorseForm from '@/modules/horses/HorseForm'
import BookingList from '@/modules/bookings/BookingList'
import BookingForm from '@/modules/bookings/BookingForm'
import ScaleBookingForm from '@/modules/scale/ScaleBookingForm'
import ScaleBookingList from '@/modules/scale/ScaleBookingList'
import AdminScaleBookingList from '@/modules/scale/AdminScaleBookingList'
import AppointmentList from '@/modules/appointments/AppointmentList'
import AppointmentForm from '@/modules/appointments/AppointmentForm'
import AppointmentAdmin from '@/modules/appointments/AppointmentAdmin'
import InviteAccept from '@/pages/InviteAccept'
import InvoiceDetail from '@/pages/InvoiceDetail'
import Invoices from '@/pages/Invoices'
import Register from '@/pages/Register'
import RekoDocList from '@/modules/rekoDocs/RekoDocList'
import RekoDocForm from '@/modules/rekoDocs/RekoDocForm'

function App() {
  return (
    <>
      <Toaster position="top-right" richColors theme="light" />
      <Routes>
        <Route path="/login" element={<Login />} />
        <Route path="/register" element={<Register />} />
        <Route path="/accept-invite" element={<InviteAccept />} />
        <Route
          path="/dashboard"
          element={
            <RequireAuth>
              <DashboardLayout>
                <Dashboard />
              </DashboardLayout>
            </RequireAuth>
          }
        />
        <Route
          path="/dashboard/bookings"
          element={
            <RequireAuth>
              <DashboardLayout>
                <BookingDashboard />
              </DashboardLayout>
            </RequireAuth>
          }
        />
        <Route
          path="/dashboard/settings"
          element={
            <RequireAuth>
              <DashboardLayout>
                <DashboardSettings />
              </DashboardLayout>
            </RequireAuth>
          }
        />
        <Route element={<PrivateRoute />}>
          <Route path="/bookings" element={<BookingList />} />
          <Route path="/bookings/new" element={<BookingForm />} />
          <Route path="/scale/book" element={<ScaleBookingForm />} />
          <Route path="/scale/my" element={<ScaleBookingList />} />
          <Route path="/appointments" element={<AppointmentList />} />
          <Route path="/appointments/new" element={<AppointmentForm />} />
          <Route path="/invoices" element={<Invoices />} />
          <Route path="/invoices/:id" element={<InvoiceDetail />} />
          <Route path="/reko/:bookingId/docs" element={<RekoDocList />} />
          <Route path="/reko/:bookingId/docs/new" element={<RekoDocForm />} />
        </Route>
        <Route element={<PrivateRoute roles={['admin', 'staff']} />}>
          <Route path="/admin/subscriptions" element={<SubscriptionList />} />
          <Route path="/admin/subscriptions/new" element={<SubscriptionForm />} />
          <Route path="/admin/horses" element={<HorseList />} />
          <Route path="/admin/horses/new" element={<HorseForm />} />
          <Route path="/admin/scale" element={<AdminScaleBookingList />} />
          <Route path="/admin/appointments" element={<AppointmentAdmin />} />
        </Route>
        <Route path="*" element={<Navigate to="/dashboard" replace />} />
      </Routes>
    </>
  )
}

export default App
