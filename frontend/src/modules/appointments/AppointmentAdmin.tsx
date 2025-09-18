import { useEffect, useState } from 'react'
import { useTranslation } from 'react-i18next'
import { useAppDispatch, useAppSelector } from '../../store'
import {
  loadProviders,
  loadServiceTypes,
  loadAppointments,
  confirm,
  complete,
  cancel,
  selectAppointments,
  selectProviders,
  selectServiceTypes,
} from '../../store/appointmentsSlice'

function AppointmentAdmin() {
  const dispatch = useAppDispatch()
  const appointments = useAppSelector(selectAppointments)
  const providers = useAppSelector(selectProviders)
  const serviceTypes = useAppSelector(selectServiceTypes)
  const { t } = useTranslation()
  const [providerFilter, setProviderFilter] = useState('')
  const [serviceFilter, setServiceFilter] = useState('')

  useEffect(() => {
    dispatch(loadProviders())
    dispatch(loadServiceTypes())
    dispatch(loadAppointments())
  }, [dispatch])

  const filtered = appointments.filter(a => {
    const providerMatches = providerFilter
      ? a.provider?.id === parseInt(providerFilter, 10)
      : true
    const serviceMatches = serviceFilter
      ? a.serviceType.id === parseInt(serviceFilter, 10)
      : true
    return providerMatches && serviceMatches
  })

  return (
    <div className="p-4">
      <h1 className="text-2xl mb-4">{t('appointments.admin')}</h1>
      <div className="flex space-x-4 mb-4">
        <select
          value={providerFilter}
          onChange={e => setProviderFilter(e.target.value)}
          className="border p-2"
        >
          <option value="">{t('appointments.filter_provider')}</option>
          {providers.map(p => (
            <option key={p.id} value={p.id}>
              {p.name}
            </option>
          ))}
        </select>
        <select
          value={serviceFilter}
          onChange={e => setServiceFilter(e.target.value)}
          className="border p-2"
        >
          <option value="">{t('appointments.filter_service')}</option>
          {serviceTypes.map(s => (
            <option key={s.id} value={s.id}>
              {s.name}
            </option>
          ))}
        </select>
      </div>
      <table className="w-full border">
        <thead>
          <tr className="bg-gray-100">
            <th className="p-2 border">{t('appointments.date')}</th>
            <th className="p-2 border">{t('appointments.provider')}</th>
            <th className="p-2 border">{t('appointments.service_type')}</th>
            <th className="p-2 border">{t('appointments.status')}</th>
            <th className="p-2 border">{t('appointments.actions')}</th>
          </tr>
        </thead>
        <tbody>
          {filtered.map(a => (
            <tr key={a.id} className="text-center">
              <td className="border p-2">{new Date(a.start).toLocaleString()}</td>
              <td className="border p-2">{a.provider?.name ?? '-'}</td>
              <td className="border p-2">{a.serviceType.name}</td>
              <td className="border p-2">{a.status}</td>
              <td className="border p-2 space-x-2">
                <button
                  onClick={() => dispatch(confirm(a.id))}
                  className="px-2 py-1 bg-green-500 text-white"
                >
                  {t('appointments.confirm')}
                </button>
                <button
                  onClick={() => dispatch(complete(a.id))}
                  className="px-2 py-1 bg-blue-500 text-white"
                >
                  {t('appointments.complete')}
                </button>
                <button
                  onClick={() => dispatch(cancel(a.id))}
                  className="px-2 py-1 bg-red-500 text-white"
                >
                  {t('appointments.cancel')}
                </button>
              </td>
            </tr>
          ))}
        </tbody>
      </table>
    </div>
  )
}

export default AppointmentAdmin

