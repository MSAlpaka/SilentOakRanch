import { useEffect, useState } from 'react'
import { Link } from 'react-router-dom'
import api from '../../axios'
import useMockData from './useMockData'
import { SubscriptionData } from './types'
import { useTranslation } from 'react-i18next'

function SubscriptionList() {
  const mockData = useMockData()
  const [subs, setSubs] = useState<SubscriptionData[]>([])
  const [loading, setLoading] = useState(false)
  const [error, setError] = useState<string | null>(null)
  const { t } = useTranslation()

  useEffect(() => {
    async function fetchData() {
      setLoading(true)
      try {
        const res = await api.get('/subscriptions')
        setSubs(res.data as SubscriptionData[])
      } catch (err) {
        setSubs(mockData)
        setError(t('subscriptions.error_load'))
      } finally {
        setLoading(false)
      }
    }
    fetchData()
  }, [mockData])

  return (
    <div className="p-4">
      <div className="flex justify-between mb-4">
        <h1 className="text-2xl">{t('subscriptions.active_title')}</h1>
        <Link
          to="/admin/subscriptions/new"
          className="bg-blue-500 text-white px-4 py-2 rounded"
        >
          {t('subscriptions.new')}
        </Link>
      </div>
      {loading && <p>{t('subscriptions.loading')}</p>}
      {error && <p className="text-red-500">{error}</p>}
      <table className="min-w-full bg-white">
        <thead>
          <tr>
            <th className="py-2 px-4 text-left">{t('subscriptions.type')}</th>
            <th className="py-2 px-4 text-left">{t('subscriptions.title')}</th>
            <th className="py-2 px-4 text-left">{t('subscriptions.amount')}</th>
            <th className="py-2 px-4 text-left">{t('subscriptions.start')}</th>
            <th className="py-2 px-4 text-left">{t('subscriptions.autorenew')}</th>
            <th className="py-2 px-4 text-left">{t('subscriptions.end')}</th>
          </tr>
        </thead>
        <tbody>
          {subs.map(s => (
            <tr key={s.id} className="border-t">
              <td className="py-2 px-4">{s.type}</td>
              <td className="py-2 px-4">{s.title}</td>
              <td className="py-2 px-4">{s.amount.toFixed ? s.amount.toFixed(2) : s.amount}</td>
              <td className="py-2 px-4">{new Date(s.startsAt).toLocaleDateString()}</td>
              <td className="py-2 px-4">{s.autoRenew ? t('common.yes') : t('common.no')}</td>
              <td className="py-2 px-4">
                {s.endDate ? new Date(s.endDate).toLocaleDateString() : '-'}
              </td>
            </tr>
          ))}
        </tbody>
      </table>
    </div>
  )
}

export default SubscriptionList
