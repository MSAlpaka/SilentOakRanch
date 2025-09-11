import { logout } from '../auth/authSlice'
import { useAppDispatch } from '../../store'

function Dashboard() {
  const dispatch = useAppDispatch()

  function handleLogout() {
    dispatch(logout())
  }

  return (
    <div className="min-h-screen">
      <header className="bg-gray-800 text-white p-4 flex justify-between">
        <span>Logged in</span>
        <button onClick={handleLogout} className="text-sm underline">
          Logout
        </button>
      </header>
      <div className="p-4">Dashboard</div>
    </div>
  )
}

export default Dashboard
