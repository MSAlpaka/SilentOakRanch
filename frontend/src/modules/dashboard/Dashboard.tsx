import { logout } from '../auth/authSlice'
import { useAppDispatch } from '../../store'
import { useTranslation } from 'react-i18next'
import { useEffect, useState } from 'react'
import api from '../../axios'

interface Horse {
  id: number
  name: string
}

interface Booking {
  id: number
  stallUnit?: { label: string }
  startDate: string
  endDate: string
}

interface Invoice {
  id: number
  number: string
  amount: string
  status: string
}

function Dashboard() {
  const dispatch = useAppDispatch()
  const { t } = useTranslation()
  const [horses, setHorses] = useState<Horse[]>([])
  const [bookings, setBookings] = useState<Booking[]>([])
  const [invoices, setInvoices] = useState<Invoice[]>([])

  useEffect(() => {
    api.get('/horses').then(res => setHorses(res.data))
    api.get('/my/bookings').then(res => setBookings(res.data))
    api.get('/my/invoices').then(res => setInvoices(res.data))
  }, [])

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
      <div className="p-4 space-y-8">
        <section>
          <h2 className="text-xl mb-2">{t('dashboard.my_horses', { defaultValue: 'My horses' })}</h2>
          <ul className="list-disc pl-5">
            {horses.map(h => (
              <li key={h.id}>{h.name}</li>
            ))}
          </ul>
        </section>
        <section>
          <h2 className="text-xl mb-2">{t('dashboard.my_bookings', { defaultValue: 'My bookings' })}</h2>
          <ul className="list-disc pl-5">
            {bookings.map(b => (
              <li key={b.id}>
                {b.stallUnit?.label} –
                {` ${new Date(b.startDate).toLocaleDateString()} - ${new Date(b.endDate).toLocaleDateString()}`}
              </li>
            ))}
          </ul>
        </section>
        <section>
          <h2 className="text-xl mb-2">{t('dashboard.my_invoices', { defaultValue: 'My invoices' })}</h2>
          <ul className="list-disc pl-5">
            {invoices.map(i => (
              <li key={i.id}>
                {i.number} - {i.amount} ({i.status})
              </li>
            ))}
          </ul>
        </section>
      </div>
    </div>
  )
}

export default Dashboard
