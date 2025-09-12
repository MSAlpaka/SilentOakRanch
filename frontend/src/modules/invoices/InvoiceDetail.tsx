import { useEffect } from 'react'
import { useParams } from 'react-router-dom'
import { useAppDispatch, useAppSelector } from '../../store'
import { fetchById } from './invoiceSlice'
import { useTranslation } from 'react-i18next'

function InvoiceDetail() {
  const { id } = useParams()
  const dispatch = useAppDispatch()
  const { t } = useTranslation()
  const invoice = useAppSelector(state => state.invoices.current)

  useEffect(() => {
    if (id) {
      dispatch(fetchById(Number(id)))
    }
  }, [dispatch, id])

  if (!invoice) {
    return <div className="p-4">{t('invoices.loading', { defaultValue: 'Loading...' })}</div>
  }

  function handleDownload() {
    window.open(invoice.downloadUrl, '_blank')
  }

  return (
    <div className="p-4">
      <h1 className="text-2xl mb-4">{t('invoices.details', { defaultValue: 'Invoice Details' })}</h1>
      <p>
        <strong>{t('invoices.date', { defaultValue: 'Date' })}:</strong>{' '}
        {new Date(invoice.createdAt).toLocaleDateString()}
      </p>
      <p>
        <strong>{t('invoices.amount', { defaultValue: 'Amount' })}:</strong> {invoice.amount}
      </p>
      <p>
        <strong>{t('invoices.status', { defaultValue: 'Status' })}:</strong> {invoice.status}
      </p>
      <button onClick={handleDownload} className="mt-4 bg-blue-500 text-white px-4 py-2">
        {t('invoices.download', { defaultValue: 'Download' })}
      </button>
    </div>
  )
}

export default InvoiceDetail
