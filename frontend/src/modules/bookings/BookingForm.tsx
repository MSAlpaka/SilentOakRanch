import { useState } from 'react'
import { useNavigate } from 'react-router-dom'
import api from '../../axios'
import { useTranslation } from 'react-i18next'

const horses = [
  { id: 1, label: 'Horse 1' },
  { id: 2, label: 'Horse 2' },
]

const packages = [
  { id: 1, label: 'Package A' },
  { id: 2, label: 'Package B' },
]

function BookingForm() {
  const navigate = useNavigate()
  const today = new Date().toISOString().split('T')[0]
  const [horseId, setHorseId] = useState('')
  const [packageId, setPackageId] = useState('')
  const [startDate, setStartDate] = useState(today)
  const [error, setError] = useState<string | null>(null)
  const { t } = useTranslation()

  async function handleSubmit(e: React.FormEvent) {
    e.preventDefault()
    try {
      await api.post('/bookings', {
        horseId: horseId ? parseInt(horseId, 10) : undefined,
        packageId: packageId ? parseInt(packageId, 10) : undefined,
        startDate,
      })
      navigate('/bookings')
    } catch (err) {
      setError(t('bookings.error_create'))
    }
  }

  return (
    <form onSubmit={handleSubmit} className="p-4 space-y-4 bg-white">
      <h1 className="text-2xl">{t('bookings.title_new')}</h1>
      {error && <p className="text-red-500">{error}</p>}
      <div>
        <label className="block mb-1">{t('bookings.horse')}</label>
        <select value={horseId} onChange={e => setHorseId(e.target.value)} className="border p-2 w-full">
          <option value="">{t('bookings.select_horse')}</option>
          {horses.map(h => (
            <option key={h.id} value={h.id}>{h.label}</option>
          ))}
        </select>
      </div>
      <div>
        <label className="block mb-1">{t('bookings.package')}</label>
        <select value={packageId} onChange={e => setPackageId(e.target.value)} className="border p-2 w-full">
          <option value="">{t('bookings.select_package')}</option>
          {packages.map(p => (
            <option key={p.id} value={p.id}>{p.label}</option>
          ))}
        </select>
      </div>
      <div>
        <label className="block mb-1">{t('bookings.start_date')}</label>
        <input type="date" value={startDate} onChange={e => setStartDate(e.target.value)} className="border p-2 w-full" />
      </div>
      <button className="bg-blue-500 text-white px-4 py-2">{t('bookings.book')}</button>
    </form>
  )
}

export default BookingForm
