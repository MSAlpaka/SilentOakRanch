import { logout } from '../auth/authSlice'
import { useAppDispatch } from '../../store'
import { useTranslation } from 'react-i18next'

function Dashboard() {
  const dispatch = useAppDispatch()
  const { t } = useTranslation()

  function handleLogout() {
    dispatch(logout())
  }

  return (
    <div className="min-h-screen">
      <header className="bg-gray-800 text-white p-4 flex justify-between">
        <span>{t('dashboard.logged_in')}</span>
        <button onClick={handleLogout} className="text-sm underline">
          {t('dashboard.logout')}
        </button>
      </header>
      <div className="p-4">{t('dashboard.title')}</div>
    </div>
  )
}

export default Dashboard
