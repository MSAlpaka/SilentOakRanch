import { useEffect } from 'react'
import { useTranslation } from 'react-i18next'
import { useAppDispatch, useAppSelector } from '../../store'
import { loadAppointments, selectAppointments } from '../../store/appointmentsSlice'

function AppointmentList() {
  const dispatch = useAppDispatch()
  const appointments = useAppSelector(selectAppointments)
  const { t } = useTranslation()

  useEffect(() => {
    dispatch(loadAppointments())
  }, [dispatch])

  return (
    <div className="p-4">
      <h1 className="text-2xl mb-4">{t('appointments.my_appointments')}</h1>
      <table className="w-full border">
        <thead>
          <tr className="bg-gray-100">
            <th className="p-2 border">{t('appointments.date')}</th>
            <th className="p-2 border">{t('appointments.provider')}</th>
            <th className="p-2 border">{t('appointments.service_type')}</th>
            <th className="p-2 border">{t('appointments.status')}</th>
            <th className="p-2 border">{t('appointments.notes')}</th>
          </tr>
        </thead>
        <tbody>
          {appointments.map(a => (
            <tr key={a.id} className="text-center">
              <td className="border p-2">{new Date(a.start).toLocaleString()}</td>
              <td className="border p-2">{a.provider?.name ?? '-'}</td>
              <td className="border p-2">{a.serviceType.name}</td>
              <td className="border p-2">{a.status}</td>
              <td className="border p-2">
                {a.status === 'confirmed' && a.provider && (
                  <div className="font-semibold">
                    {t('appointment.confirmation.subject', {
                      providerName: a.provider.name,
                      date: new Date(a.start).toLocaleDateString(),
                    })}
                  </div>
                )}
                {a.notes}
              </td>
            </tr>
          ))}
        </tbody>
      </table>
    </div>
  )
}

export default AppointmentList

