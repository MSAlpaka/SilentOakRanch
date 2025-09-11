import { useEffect, useState } from 'react'
import api from '../../axios'

interface ScaleBooking {
  id: string
  horse?: { name: string } | string | null
  slot: string
  status: string
  weight?: number | null
}

function AdminScaleBookingList() {
  const [bookings, setBookings] = useState<ScaleBooking[]>([])
  const [weights, setWeights] = useState<Record<string, string>>({})

  function load() {
    api.get('/scale/bookings').then(res => setBookings(res.data))
  }

  useEffect(() => {
    load()
  }, [])

  function confirm(id: string) {
    api.post(`/scale/bookings/${id}/confirm`).then(load)
  }

  function submitWeight(id: string) {
    const weight = parseFloat(weights[id])
    if (Number.isNaN(weight)) return
    api.post(`/scale/bookings/${id}/weight`, { weight }).then(() => {
      setWeights(prev => ({ ...prev, [id]: '' }))
      load()
    })
  }

  return (
    <div className="p-4">
      <h1 className="text-2xl mb-4">Scale Bookings</h1>
      <table className="w-full border">
        <thead>
          <tr className="bg-gray-100">
            <th className="p-2 border">Horse</th>
            <th className="p-2 border">Slot</th>
            <th className="p-2 border">Status</th>
            <th className="p-2 border">Weight</th>
            <th className="p-2 border">Actions</th>
          </tr>
        </thead>
        <tbody>
          {bookings.map(b => (
            <tr key={b.id} className="text-center">
              <td className="border p-2">{typeof b.horse === 'string' ? b.horse : b.horse?.name}</td>
              <td className="border p-2">{new Date(b.slot).toLocaleString()}</td>
              <td className="border p-2">{b.status}</td>
              <td className="border p-2">{b.weight ?? '-'}</td>
              <td className="border p-2">
                {b.status === 'pending' && (
                  <button
                    className="bg-blue-500 text-white px-2 py-1"
                    onClick={() => confirm(b.id)}
                  >
                    Confirm
                  </button>
                )}
                {b.status === 'confirmed' && (
                  <div className="flex justify-center space-x-2">
                    <input
                      type="number"
                      className="border p-1 w-24"
                      value={weights[b.id] || ''}
                      onChange={e =>
                        setWeights({ ...weights, [b.id]: e.target.value })
                      }
                      placeholder="Weight"
                    />
                    <button
                      className="bg-green-500 text-white px-2 py-1"
                      onClick={() => submitWeight(b.id)}
                    >
                      Save
                    </button>
                  </div>
                )}
              </td>
            </tr>
          ))}
        </tbody>
      </table>
    </div>
  )
}

export default AdminScaleBookingList
