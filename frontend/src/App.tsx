import { Routes, Route, Navigate } from 'react-router-dom'
import Login from './modules/auth/Login'
import Dashboard from './modules/dashboard/Dashboard'
import { useAuth } from './modules/auth/AuthContext'

function App() {
  const { token } = useAuth()

  return (
    <Routes>
      <Route path="/login" element={<Login />} />
      <Route
        path="/dashboard"
        element={token ? <Dashboard /> : <Navigate to="/login" replace />}
      />
      <Route path="*" element={<Navigate to="/dashboard" replace />} />
    </Routes>
  )
}

export default App
