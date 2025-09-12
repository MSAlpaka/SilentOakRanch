import { useEffect } from 'react'
import { useAppDispatch, useAppSelector } from '../../store'
import { fetchAll } from './invoiceSlice'
import { useTranslation } from 'react-i18next'

function InvoiceList() {
  const dispatch = useAppDispatch()
  const { t } = useTranslation()
  const invoices = useAppSelector(state => state.invoices.items)

  useEffect(() => {
    dispatch(fetchAll())
  }, [dispatch])

  return (
    <div className="p-4">
      <h1 className="text-2xl mb-4">{t('invoices.title', { defaultValue: 'Invoices' })}</h1>
      <table className="w-full border">
        <thead>
          <tr className="bg-gray-100">
            <th className="p-2 border">{t('invoices.date', { defaultValue: 'Date' })}</th>
            <th className="p-2 border">{t('invoices.amount', { defaultValue: 'Amount' })}</th>
            <th className="p-2 border">{t('invoices.status', { defaultValue: 'Status' })}</th>
            <th className="p-2 border">{t('invoices.download', { defaultValue: 'Download' })}</th>
          </tr>
        </thead>
        <tbody>
          {invoices.map(inv => (
            <tr key={inv.id} className="text-center">
              <td className="border p-2">{new Date(inv.createdAt).toLocaleDateString()}</td>
              <td className="border p-2">{inv.amount}</td>
              <td className="border p-2">{inv.status}</td>
              <td className="border p-2">
                <a
                  href={inv.downloadUrl}
                  target="_blank"
                  rel="noopener noreferrer"
                  className="text-blue-500 underline"
                >
                  {t('invoices.download', { defaultValue: 'Download' })}
                </a>
              </td>
            </tr>
          ))}
        </tbody>
      </table>
    </div>
  )
}

export default InvoiceList
