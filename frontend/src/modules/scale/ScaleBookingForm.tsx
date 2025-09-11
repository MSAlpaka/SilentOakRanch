import { useEffect, useState } from 'react'
import { useNavigate } from 'react-router-dom'
import api from '../../axios'

interface Horse {
  id: number
  name: string
}

const bookingTypes = [
  { value: 'single', label: 'Single' },
  { value: 'package', label: 'Package' },
  { value: 'premium', label: 'Premium' },
  { value: 'dynamic', label: 'Dynamic' },
]

function ScaleBookingForm() {
  const navigate = useNavigate()
  const today = new Date().toISOString().split('T')[0]
  const [horses, setHorses] = useState<Horse[]>([])
  const [horseId, setHorseId] = useState('')
  const [day, setDay] = useState(today)
  const [slots, setSlots] = useState<string[]>([])
  const [slot, setSlot] = useState('')
  const [type, setType] = useState('single')
  const [error, setError] = useState<string | null>(null)

  useEffect(() => {
    api.get('/horses').then(res => setHorses(res.data))
  }, [])

  useEffect(() => {
    api.get(`/scale/slots?day=${day}`).then(res => setSlots(res.data))
  }, [day])

  async function handleSubmit(e: React.FormEvent) {
    e.preventDefault()
    try {
      await api.post('/scale/bookings', {
        horseId: horseId ? parseInt(horseId, 10) : undefined,
        slot,
        type,
      })
      navigate('/scale/my')
    } catch {
      setError('Failed to create booking')
    }
  }

  return (
    <form onSubmit={handleSubmit} className="p-4 space-y-4 bg-white">
      <h1 className="text-2xl">Book Scale Slot</h1>
      {error && <p className="text-red-500">{error}</p>}
      <div>
        <label className="block mb-1">Horse</label>
        <select
          value={horseId}
          onChange={e => setHorseId(e.target.value)}
          className="border p-2 w-full"
        >
          <option value="">Select horse</option>
          {horses.map(h => (
            <option key={h.id} value={h.id}>
              {h.name}
            </option>
          ))}
        </select>
      </div>
      <div>
        <label className="block mb-1">Day</label>
        <input
          type="date"
          value={day}
          onChange={e => setDay(e.target.value)}
          className="border p-2 w-full"
        />
      </div>
      <div>
        <label className="block mb-1">Slot</label>
        <select
          value={slot}
          onChange={e => setSlot(e.target.value)}
          className="border p-2 w-full"
        >
          <option value="">Select slot</option>
          {slots.map(s => (
            <option key={s} value={s}>
              {new Date(s).toLocaleTimeString()}
            </option>
          ))}
        </select>
      </div>
      <div>
        <label className="block mb-1">Type</label>
        <select
          value={type}
          onChange={e => setType(e.target.value)}
          className="border p-2 w-full"
        >
          {bookingTypes.map(bt => (
            <option key={bt.value} value={bt.value}>
              {bt.label}
            </option>
          ))}
        </select>
      </div>
      <button className="bg-blue-500 text-white px-4 py-2">Book</button>
    </form>
  )
}

export default ScaleBookingForm
