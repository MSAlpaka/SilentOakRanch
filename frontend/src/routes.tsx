import { RouteObject } from 'react-router-dom'
import AppointmentForm from './modules/appointments/AppointmentForm'
import AppointmentList from './modules/appointments/AppointmentList'
import AppointmentAdmin from './modules/appointments/AppointmentAdmin'

export const appointmentRoutes: RouteObject[] = [
  { path: '/appointments', element: <AppointmentList /> },
  { path: '/appointments/new', element: <AppointmentForm /> },
  { path: '/admin/appointments', element: <AppointmentAdmin /> },
]

