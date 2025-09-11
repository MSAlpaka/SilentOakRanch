import { useState } from 'react'
import { useNavigate } from 'react-router-dom'
import api from '../../axios'

function HorseForm() {
  const navigate = useNavigate()
  const [name, setName] = useState('')
  const [age, setAge] = useState('')
  const [breed, setBreed] = useState('')
  const [showReko, setShowReko] = useState(false)
  const [specialNotes, setSpecialNotes] = useState('')
  const [medicalHistory, setMedicalHistory] = useState('')
  const [medication, setMedication] = useState('')
  const [error, setError] = useState<string | null>(null)

  async function handleSubmit(e: React.FormEvent) {
    e.preventDefault()
    const payload: any = {
      name,
      age: parseInt(age, 10),
      breed,
    }
    if (showReko) {
      if (specialNotes) payload.specialNotes = specialNotes
      if (medicalHistory) payload.medicalHistory = medicalHistory
      if (medication) payload.medication = medication
    }
    try {
      await api.post('/horses', payload)
      navigate('/admin/horses')
    } catch {
      setError('Failed to save horse')
    }
  }

  return (
    <form onSubmit={handleSubmit} className="p-4 space-y-4 bg-white">
      <h1 className="text-2xl">New Horse</h1>
      {error && <p className="text-red-500">{error}</p>}
      <div>
        <label className="block mb-1">Name</label>
        <input value={name} onChange={e => setName(e.target.value)} className="border p-2 w-full" />
      </div>
      <div>
        <label className="block mb-1">Age</label>
        <input type="number" value={age} onChange={e => setAge(e.target.value)} className="border p-2 w-full" />
      </div>
      <div>
        <label className="block mb-1">Breed</label>
        <input value={breed} onChange={e => setBreed(e.target.value)} className="border p-2 w-full" />
      </div>
      <div>
        <label className="inline-flex items-center">
          <input type="checkbox" checked={showReko} onChange={e => setShowReko(e.target.checked)} className="mr-2" />
          Add Reko Info
        </label>
      </div>
      {showReko && (
        <>
          <div>
            <label className="block mb-1">Special Notes</label>
            <textarea value={specialNotes} onChange={e => setSpecialNotes(e.target.value)} className="border p-2 w-full" />
          </div>
          <div>
            <label className="block mb-1">Medical History</label>
            <textarea value={medicalHistory} onChange={e => setMedicalHistory(e.target.value)} className="border p-2 w-full" />
          </div>
          <div>
            <label className="block mb-1">Medication</label>
            <textarea value={medication} onChange={e => setMedication(e.target.value)} className="border p-2 w-full" />
          </div>
        </>
      )}
      <button className="bg-blue-500 text-white px-4 py-2">Save Horse</button>
    </form>
  )
}

export default HorseForm
