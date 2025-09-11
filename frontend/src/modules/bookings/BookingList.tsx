import { useEffect, useState } from 'react'
import api from '../../axios'
import PaymentButton from './PaymentButton'
import { useTranslation } from 'react-i18next'

interface Booking {
  id: number
  horse?: string | null
  stallUnit?: { label: string }
  startDate: string
  endDate: string
  status: string
  price?: string
}

function BookingList() {
  const [bookings, setBookings] = useState<Booking[]>([])
  const { t } = useTranslation()

  useEffect(() => {
    api.get('/my/bookings').then(res => setBookings(res.data))
  }, [])

  return (
    <div className="p-4">
      <h1 className="text-2xl mb-4">{t('bookings.my_bookings')}</h1>
      <table className="w-full border">
        <thead>
          <tr className="bg-gray-100">
            <th className="p-2 border">{t('bookings.horse')}</th>
            <th className="p-2 border">{t('bookings.stall')}</th>
            <th className="p-2 border">{t('bookings.start')}</th>
            <th className="p-2 border">{t('bookings.end')}</th>
            <th className="p-2 border">{t('bookings.status')}</th>
            <th className="p-2 border">{t('bookings.price')}</th>
            <th className="p-2 border">{t('bookings.actions')}</th>
          </tr>
        </thead>
        <tbody>
          {bookings.map(b => (
            <tr key={b.id} className="text-center">
              <td className="border p-2">{b.horse || '-'}</td>
              <td className="border p-2">{b.stallUnit?.label}</td>
              <td className="border p-2">{new Date(b.startDate).toLocaleDateString()}</td>
              <td className="border p-2">{new Date(b.endDate).toLocaleDateString()}</td>
              <td className="border p-2">{b.status}</td>
              <td className="border p-2">{b.price}</td>
              <td className="border p-2">{b.price && <PaymentButton bookingId={b.id} />}</td>
            </tr>
          ))}
        </tbody>
      </table>
    </div>
  )
}

export default BookingList
