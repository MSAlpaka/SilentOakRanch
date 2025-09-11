import { useEffect, useState } from 'react'
import api from '../../axios'
import PaymentButton from './PaymentButton'

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

  useEffect(() => {
    api.get('/my/bookings').then(res => setBookings(res.data))
  }, [])

  return (
    <div className="p-4">
      <h1 className="text-2xl mb-4">My Bookings</h1>
      <table className="w-full border">
        <thead>
          <tr className="bg-gray-100">
            <th className="p-2 border">Horse</th>
            <th className="p-2 border">Stall</th>
            <th className="p-2 border">Start</th>
            <th className="p-2 border">End</th>
            <th className="p-2 border">Status</th>
            <th className="p-2 border">Price</th>
            <th className="p-2 border">Actions</th>
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
