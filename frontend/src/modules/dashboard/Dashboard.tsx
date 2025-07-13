import { useAuth } from '../auth/AuthContext'

function AdminView() {
  return <div className="p-4">Admin Dashboard</div>
}

function StaffView() {
  return <div className="p-4">Staff Dashboard</div>
}

function CustomerView() {
  return <div className="p-4">Customer Dashboard</div>
}

function Dashboard() {
  const { role, user, logout } = useAuth()

  let content: JSX.Element | null = null
  switch (role) {
    case 'admin':
      content = <AdminView />
      break
    case 'staff':
      content = <StaffView />
      break
    case 'customer':
      content = <CustomerView />
      break
    default:
      content = null
  }

  return (
    <div className="min-h-screen">
      <header className="bg-gray-800 text-white p-4 flex justify-between">
        <span>Logged in as {user?.firstName ?? user?.email}</span>
        <button onClick={logout} className="text-sm underline">
          Logout
        </button>
      </header>
      {content}
    </div>
  )
}

export default Dashboard
