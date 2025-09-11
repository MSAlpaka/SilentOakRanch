import { useState } from 'react'
import { useNavigate } from 'react-router-dom'
import api from '../../axios'
import { useTranslation } from 'react-i18next'

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
  const { t } = useTranslation()

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
      setError(t('horses.error_save'))
    }
  }

  return (
    <form onSubmit={handleSubmit} className="p-4 space-y-4 bg-white">
      <h1 className="text-2xl">{t('horses.new')}</h1>
      {error && <p className="text-red-500">{error}</p>}
      <div>
        <label className="block mb-1">{t('horses.name')}</label>
        <input value={name} onChange={e => setName(e.target.value)} className="border p-2 w-full" />
      </div>
      <div>
        <label className="block mb-1">{t('horses.age')}</label>
        <input type="number" value={age} onChange={e => setAge(e.target.value)} className="border p-2 w-full" />
      </div>
      <div>
        <label className="block mb-1">{t('horses.breed')}</label>
        <input value={breed} onChange={e => setBreed(e.target.value)} className="border p-2 w-full" />
      </div>
      <div>
        <label className="inline-flex items-center">
          <input type="checkbox" checked={showReko} onChange={e => setShowReko(e.target.checked)} className="mr-2" />
          {t('horses.add_reko')}
        </label>
      </div>
      {showReko && (
        <>
          <div>
            <label className="block mb-1">{t('horses.special_notes')}</label>
            <textarea value={specialNotes} onChange={e => setSpecialNotes(e.target.value)} className="border p-2 w-full" />
          </div>
          <div>
            <label className="block mb-1">{t('horses.medical_history')}</label>
            <textarea value={medicalHistory} onChange={e => setMedicalHistory(e.target.value)} className="border p-2 w-full" />
          </div>
          <div>
            <label className="block mb-1">{t('horses.medication')}</label>
            <textarea value={medication} onChange={e => setMedication(e.target.value)} className="border p-2 w-full" />
          </div>
        </>
      )}
      <button className="bg-blue-500 text-white px-4 py-2">{t('horses.save')}</button>
    </form>
  )
}

export default HorseForm
