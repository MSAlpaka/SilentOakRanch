import { useEffect, useState } from 'react'
import api from '../../axios'

interface ScaleBooking {
  id: string
  horse?: { name: string } | string | null
  slot: string
  status: string
  qrToken: string
  weight?: number | null
}

function ScaleBookingList() {
  const [bookings, setBookings] = useState<ScaleBooking[]>([])

  useEffect(() => {
    api.get('/scale/bookings/my').then(res => setBookings(res.data))
  }, [])

  return (
    <div className="p-4">
      <h1 className="text-2xl mb-4">My Scale Bookings</h1>
      <table className="w-full border">
        <thead>
          <tr className="bg-gray-100">
            <th className="p-2 border">Horse</th>
            <th className="p-2 border">Slot</th>
            <th className="p-2 border">Status</th>
            <th className="p-2 border">QR</th>
            <th className="p-2 border">Weight</th>
          </tr>
        </thead>
        <tbody>
          {bookings.map(b => (
            <tr key={b.id} className="text-center">
              <td className="border p-2">{typeof b.horse === 'string' ? b.horse : b.horse?.name}</td>
              <td className="border p-2">{new Date(b.slot).toLocaleString()}</td>
              <td className="border p-2">{b.status}</td>
              <td className="border p-2">
                {b.qrToken && (
                  <img
                    src={`https://api.qrserver.com/v1/create-qr-code/?size=80x80&data=${b.qrToken}`}
                    alt="QR"
                    className="mx-auto"
                  />
                )}
              </td>
              <td className="border p-2">{b.weight ?? '-'}</td>
            </tr>
          ))}
        </tbody>
      </table>
    </div>
  )
}

export default ScaleBookingList
