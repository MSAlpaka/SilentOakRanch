import { useEffect, useMemo, useState } from 'react'
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

function formatForDateTimeLocal(date: Date) {
  const year = date.getFullYear()
  const month = `${date.getMonth() + 1}`.padStart(2, '0')
  const day = `${date.getDate()}`.padStart(2, '0')
  const hours = `${date.getHours()}`.padStart(2, '0')
  const minutes = `${date.getMinutes()}`.padStart(2, '0')
  return `${year}-${month}-${day}T${hours}:${minutes}`
}

function AppointmentForm() {
  const dispatch = useAppDispatch()
  const navigate = useNavigate()
  const providers = useAppSelector(selectProviders)
  const serviceTypes = useAppSelector(selectServiceTypes)
  const appointments = useAppSelector(selectAppointments)
  const [providerId, setProviderId] = useState('')
  const [serviceTypeId, setServiceTypeId] = useState('')
  const [start, setStart] = useState('')
  const [end, setEnd] = useState('')
  const [userEditedEnd, setUserEditedEnd] = useState(false)
  const [notes, setNotes] = useState('')
  const [reminder, setReminder] = useState(false)
  const { t } = useTranslation()

  useEffect(() => {
    dispatch(loadProviders())
    dispatch(loadServiceTypes())
    dispatch(loadAppointments())
  }, [dispatch])

  const selectedServiceType = useMemo(() => {
    const id = Number.parseInt(serviceTypeId, 10)
    if (Number.isNaN(id)) return undefined
    return serviceTypes.find(s => s.id === id)
  }, [serviceTypeId, serviceTypes])

  useEffect(() => {
    setUserEditedEnd(false)
  }, [serviceTypeId])

  useEffect(() => {
    if (!start) {
      setEnd('')
    }
  }, [start])

  useEffect(() => {
    if (!start) {
      return
    }
    const duration = selectedServiceType?.defaultDurationMinutes
    if (!duration || userEditedEnd) {
      return
    }

    const startDate = new Date(start)
    if (Number.isNaN(startDate.getTime())) {
      return
    }
    const endDate = new Date(startDate.getTime() + duration * 60 * 1000)
    setEnd(formatForDateTimeLocal(endDate))
  }, [start, selectedServiceType, userEditedEnd])

  const activeAppointments = useMemo(
    () => appointments.filter(a => a.status !== 'canceled'),
    [appointments]
  )

  function handleSubmit(e: React.FormEvent) {
    e.preventDefault()
    if (!providerId || !serviceTypeId || !start || !end) {
      return
    }
    const provider = Number.parseInt(providerId, 10)
    const serviceType = Number.parseInt(serviceTypeId, 10)
    if (Number.isNaN(provider) || Number.isNaN(serviceType)) {
      return
    }
    const startDate = new Date(start)
    const endDate = new Date(end)
    if (
      Number.isNaN(startDate.getTime()) ||
      Number.isNaN(endDate.getTime()) ||
      endDate <= startDate
    ) {
      return
    }

    const collision = activeAppointments.some(a => {
      if (a.provider?.id !== provider) {
        return false
      }
      const appointmentStart = new Date(a.start)
      const appointmentEnd = new Date(a.end)
      if (
        Number.isNaN(appointmentStart.getTime()) ||
        Number.isNaN(appointmentEnd.getTime())
      ) {
        return false
      }
      return appointmentStart < endDate && appointmentEnd > startDate
    })

    if (collision) {
      return
    }

    dispatch(
      create({
        providerId: provider,
        serviceTypeId: serviceType,
        start: startDate.toISOString(),
        end: endDate.toISOString(),
        notes,
        reminderOptIn: reminder,
      })
    ).then(() => navigate('/appointments'))
  }

  return (
    <form onSubmit={handleSubmit} className="p-4 space-y-4 bg-white">
      <h1 className="text-2xl">{t('appointments.new')}</h1>
      <div>
        <label className="block mb-1" htmlFor="appointment-provider">
          {t('appointments.provider')}
        </label>
        <select
          id="appointment-provider"
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
        <label className="block mb-1" htmlFor="appointment-service-type">
          {t('appointments.service_type')}
        </label>
        <select
          id="appointment-service-type"
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
        <label className="block mb-1" htmlFor="appointment-start">
          {t('appointments.slot')}
        </label>
        <input
          id="appointment-start"
          type="datetime-local"
          value={start}
          onChange={e => {
            setStart(e.target.value)
            setUserEditedEnd(false)
          }}
          className="border p-2 w-full"
        />
      </div>
      <div>
        <label className="block mb-1" htmlFor="appointment-end">
          {t('appointments.end_time', { defaultValue: 'End time' })}
        </label>
        <input
          id="appointment-end"
          type="datetime-local"
          value={end}
          onChange={e => {
            setEnd(e.target.value)
            setUserEditedEnd(true)
          }}
          className="border p-2 w-full"
        />
      </div>
      <div>
        <label className="block mb-1" htmlFor="appointment-notes">
          {t('appointments.notes')}
        </label>
        <textarea
          id="appointment-notes"
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

