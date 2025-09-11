import { useEffect, useState } from 'react'
import { Link } from 'react-router-dom'
import api from '../../axios'

interface Horse {
  id: number
  name: string
  age: number
  breed: string
}

function HorseList() {
  const [horses, setHorses] = useState<Horse[]>([])
  const [error, setError] = useState<string | null>(null)

  useEffect(() => {
    api
      .get('/horses')
      .then(res => setHorses(res.data))
      .catch(() => setError('Failed to load horses'))
  }, [])

  return (
    <div className="p-4">
      <div className="flex justify-between items-center mb-4">
        <h1 className="text-2xl">Horses</h1>
        <Link to="/admin/horses/new" className="bg-blue-500 text-white px-4 py-2">
          New Horse
        </Link>
      </div>
      {error && <p className="text-red-500 mb-4">{error}</p>}
      <ul className="space-y-2">
        {horses.map(horse => (
          <li key={horse.id} className="border p-2">
            <span className="font-bold">{horse.name}</span> - {horse.breed} ({horse.age})
          </li>
        ))}
      </ul>
    </div>
  )
}

export default HorseList
