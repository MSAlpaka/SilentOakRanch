import { useState } from 'react'
import { useNavigate } from 'react-router-dom'
import api from '../../axios'
import { SubscriptionData } from './types'
import { useTranslation } from 'react-i18next'

const users = [
  { id: 1, label: 'User 1' },
  { id: 2, label: 'User 2' },
]

const horses = [
  { id: 1, label: 'Horse 1' },
  { id: 2, label: 'Horse 2' },
]

const stalls = [
  { id: 1, label: 'Stall A' },
  { id: 2, label: 'Stall B' },
]

function SubscriptionForm() {
  const navigate = useNavigate()
  const today = new Date().toISOString().split('T')[0]
  const [type, setType] = useState<'USER' | 'HORSE' | 'STALL'>('USER')
  const [assignment, setAssignment] = useState('')
  const [title, setTitle] = useState('')
  const [amount, setAmount] = useState('')
  const [start, setStart] = useState(today)
  const [autoRenew, setAutoRenew] = useState(true)
  const [end, setEnd] = useState('')
  const [error, setError] = useState<string | null>(null)
  const { t } = useTranslation()

  async function handleSubmit(e: React.FormEvent) {
    e.preventDefault()
    const payload: SubscriptionData & {
      userId?: number
      horseId?: number
      stallUnitId?: number
    } = {
      type,
      title: title || `Abo ${type}`,
      amount: parseFloat(amount),
      startsAt: start,
      autoRenew,
    }
    if (end) payload.endDate = end
    if (type === 'USER') payload.userId = parseInt(assignment, 10)
    if (type === 'HORSE') payload.horseId = parseInt(assignment, 10)
    if (type === 'STALL') payload.stallUnitId = parseInt(assignment, 10)

    try {
      await api.post('/subscriptions', payload)
      navigate('/admin/subscriptions')
    } catch (err) {
      setError(t('subscriptions.error_save'))
    }
  }

  let assignmentOptions
  if (type === 'USER') {
    assignmentOptions = users
  } else if (type === 'HORSE') {
    assignmentOptions = horses
  } else {
    assignmentOptions = stalls
  }

  return (
    <form onSubmit={handleSubmit} className="p-4 space-y-4 bg-white">
      <h1 className="text-2xl">{t('subscriptions.new_title')}</h1>
      {error && <p className="text-red-500">{error}</p>}
      <div>
        <label className="block mb-1">{t('subscriptions.type')}</label>
        <select value={type} onChange={e => setType(e.target.value as any)} className="border p-2 w-full">
          <option value="USER">USER</option>
          <option value="HORSE">HORSE</option>
          <option value="STALL">STALL</option>
        </select>
      </div>
      <div>
        <label className="block mb-1">{t('subscriptions.assignment')}</label>
        <select value={assignment} onChange={e => setAssignment(e.target.value)} className="border p-2 w-full">
          <option value="">{t('subscriptions.please_select')}</option>
          {assignmentOptions.map(opt => (
            <option key={opt.id} value={opt.id}>
              {opt.label}
            </option>
          ))}
        </select>
      </div>
      <div>
        <label className="block mb-1">{t('subscriptions.title')}</label>
        <input value={title} onChange={e => setTitle(e.target.value)} className="border p-2 w-full" />
      </div>
      <div>
        <label className="block mb-1">{t('subscriptions.amount')}</label>
        <input type="number" step="0.01" value={amount} onChange={e => setAmount(e.target.value)} className="border p-2 w-full" />
      </div>
      <div>
        <label className="block mb-1">{t('subscriptions.start_date')}</label>
        <input type="date" value={start} onChange={e => setStart(e.target.value)} className="border p-2 w-full" />
      </div>
      <div>
        <label className="inline-flex items-center">
          <input type="checkbox" checked={autoRenew} onChange={e => setAutoRenew(e.target.checked)} className="mr-2" />
          {t('subscriptions.autoRenew')}
        </label>
      </div>
      <div>
        <label className="block mb-1">{t('subscriptions.end_date')}</label>
        <input type="date" value={end} onChange={e => setEnd(e.target.value)} className="border p-2 w-full" />
      </div>
      <button className="bg-blue-500 text-white px-4 py-2">{t('subscriptions.save')}</button>
    </form>
  )
}

export default SubscriptionForm
