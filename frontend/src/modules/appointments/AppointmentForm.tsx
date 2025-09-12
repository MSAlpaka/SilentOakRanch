import { useEffect, useState } from 'react'
import { useNavigate } from 'react-router-dom'
import { useTranslation } from 'react-i18next'
import { useAppDispatch, useAppSelector } from '../../store'
import {
  loadProviders,
  loadServiceTypes,
  loadAppointments,
  create,
  selectAppointments,
  selectProviders,
  selectServiceTypes,
} from '../../store/appointmentsSlice'

function AppointmentForm() {
  const dispatch = useAppDispatch()
  const navigate = useNavigate()
  const providers = useAppSelector(selectProviders)
  const serviceTypes = useAppSelector(selectServiceTypes)
  const appointments = useAppSelector(selectAppointments)
  const [providerId, setProviderId] = useState('')
  const [serviceTypeId, setServiceTypeId] = useState('')
  const [start, setStart] = useState('')
  const [notes, setNotes] = useState('')
  const [reminder, setReminder] = useState(false)
  const { t } = useTranslation()

  useEffect(() => {
    dispatch(loadProviders())
    dispatch(loadServiceTypes())
    dispatch(loadAppointments())
  }, [dispatch])

  const availableSlots = appointments.filter(a => a.status === 'available')

  function handleSubmit(e: React.FormEvent) {
    e.preventDefault()
    if (!providerId || !serviceTypeId || !start) {
      return
    }
    const collision = appointments.some(
      a => a.start === start && a.status !== 'available'
    )
    if (collision) {
      return
    }
    dispatch(
      create({
        providerId: parseInt(providerId, 10),
        serviceTypeId: parseInt(serviceTypeId, 10),
        start,
        notes,
        reminderOptIn: reminder,
      })
    ).then(() => navigate('/appointments'))
  }

  return (
    <form onSubmit={handleSubmit} className="p-4 space-y-4 bg-white">
      <h1 className="text-2xl">{t('appointments.new')}</h1>
      <div>
        <label className="block mb-1">{t('appointments.provider')}</label>
        <select
          value={providerId}
          onChange={e => setProviderId(e.target.value)}
          className="border p-2 w-full"
        >
          <option value="">{t('appointments.provider')}</option>
          {providers.map(p => (
            <option key={p.id} value={p.id}>
              {p.name}
            </option>
          ))}
        </select>
      </div>
      <div>
        <label className="block mb-1">{t('appointments.service_type')}</label>
        <select
          value={serviceTypeId}
          onChange={e => setServiceTypeId(e.target.value)}
          className="border p-2 w-full"
        >
          <option value="">{t('appointments.service_type')}</option>
          {serviceTypes.map(s => (
            <option key={s.id} value={s.id}>
              {s.name}
            </option>
          ))}
        </select>
      </div>
      <div>
        <label className="block mb-1">{t('appointments.slot')}</label>
        <select
          value={start}
          onChange={e => setStart(e.target.value)}
          className="border p-2 w-full"
        >
          <option value="">{t('appointments.slot')}</option>
          {availableSlots.map(s => (
            <option key={s.id} value={s.start}>
              {new Date(s.start).toLocaleString()}
            </option>
          ))}
        </select>
      </div>
      <div>
        <label className="block mb-1">{t('appointments.notes')}</label>
        <textarea
          value={notes}
          onChange={e => setNotes(e.target.value)}
          className="border p-2 w-full"
        />
      </div>
      <div>
        <label className="inline-flex items-center">
          <input
            type="checkbox"
            checked={reminder}
            onChange={e => setReminder(e.target.checked)}
            className="mr-2"
          />
          {t('appointments.reminder')}
        </label>
      </div>
      <button className="bg-blue-500 text-white px-4 py-2">{t('appointments.save')}</button>
    </form>
  )
}

export default AppointmentForm

