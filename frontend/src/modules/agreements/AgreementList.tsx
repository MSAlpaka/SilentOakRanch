import { useEffect } from 'react'
import { useAppDispatch, useAppSelector } from '../../store'
import { loadAll } from './agreementsSlice'
import { loadOne as loadOneApi } from '../../api/agreements'
import { useTranslation } from 'react-i18next'

function AgreementList() {
  const dispatch = useAppDispatch()
  const { t } = useTranslation()
  const agreements = useAppSelector(state => state.agreements.items)

  useEffect(() => {
    dispatch(loadAll())
  }, [dispatch])

  const handleDownload = async (id: number) => {
    const blob = await loadOneApi(String(id))
    const url = window.URL.createObjectURL(blob)
    const link = document.createElement('a')
    link.href = url
    link.download = `agreement-${id}.pdf`
    document.body.appendChild(link)
    link.click()
    link.remove()
    window.URL.revokeObjectURL(url)
  }

  return (
    <div className="p-4">
      <h1 className="text-2xl mb-4">
        {t('agreements.title', { defaultValue: 'Agreements' })}
      </h1>
      <table className="w-full border">
        <thead>
          <tr className="bg-gray-100">
            <th className="p-2 border">
              {t('agreements.type', { defaultValue: 'Type' })}
            </th>
            <th className="p-2 border">
              {t('agreements.version', { defaultValue: 'Version' })}
            </th>
            <th className="p-2 border">
              {t('agreements.status', { defaultValue: 'Status' })}
            </th>
            <th className="p-2 border">
              {t('agreements.download', { defaultValue: 'Download' })}
            </th>
          </tr>
        </thead>
        <tbody>
          {agreements.map(ag => (
            <tr key={ag.id} className="text-center">
              <td className="border p-2">{ag.type}</td>
              <td className="border p-2">{ag.version}</td>
              <td className="border p-2">{ag.status}</td>
              <td className="border p-2">
                <button
                  onClick={() => handleDownload(ag.id)}
                  className="text-blue-500 underline"
                >
                  {t('agreements.download', { defaultValue: 'Download' })}
                </button>
              </td>
            </tr>
          ))}
        </tbody>
      </table>
    </div>
  )
}

export default AgreementList

