import { useEffect, useState } from 'react'
import { useParams } from 'react-router-dom'
import { loadDocs, exportDoc, RekoDoc } from '../../api/rekoDocs'
import { useTranslation } from 'react-i18next'

function RekoDocList() {
  const { bookingId } = useParams<{ bookingId: string }>()
  const [docs, setDocs] = useState<RekoDoc[]>([])
  const { t } = useTranslation()

  useEffect(() => {
    if (!bookingId) return
    loadDocs(bookingId).then(data => {
      const sorted = [...data].sort(
        (a, b) => new Date(a.createdAt).getTime() - new Date(b.createdAt).getTime()
      )
      setDocs(sorted)
    })
  }, [bookingId])

  async function handleExport(id: number) {
    const blob = await exportDoc(String(id))
    const url = URL.createObjectURL(blob)
    const a = document.createElement('a')
    a.href = url
    a.download = `reko-${id}.pdf`
    a.click()
    URL.revokeObjectURL(url)
  }

  return (
    <ul className="space-y-4">
      {docs.map(doc => (
        <li key={doc.id} data-testid="doc" className="border p-4">
          {doc.notes && <p>{doc.notes}</p>}
          {doc.photos?.map((photo, idx) => (
            <img key={idx} src={photo} alt={`photo-${idx}`} className="h-20" />
          ))}
          {doc.videos?.map((video, idx) => (
            <video key={idx} src={video} controls className="h-20" />
          ))}
          {doc.metrics && (
            <pre data-testid="metrics">{JSON.stringify(doc.metrics)}</pre>
          )}
          {doc.type === 'premium' && (
            <button onClick={() => handleExport(doc.id)}>{t('rekoDocs.export')}</button>
          )}
        </li>
      ))}
    </ul>
  )
}

export default RekoDocList
