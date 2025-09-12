import { useState } from 'react'
import { useNavigate, useParams } from 'react-router-dom'
import { createDoc, RekoDocType } from '../../api/rekoDocs'
import { useTranslation } from 'react-i18next'

function RekoDocForm() {
  const { bookingId } = useParams<{ bookingId: string }>()
  const navigate = useNavigate()
  const { t } = useTranslation()

  const [type, setType] = useState<RekoDocType>('basis')
  const [notes, setNotes] = useState('')
  const [photos, setPhotos] = useState<string[]>([])
  const [videos, setVideos] = useState<string[]>([])
  const [error, setError] = useState<string | null>(null)

  function handlePhotoChange(e: React.ChangeEvent<HTMLInputElement>) {
    const files = Array.from(e.target.files || []).map(f => f.name)
    setPhotos(files)
  }

  function handleVideoChange(e: React.ChangeEvent<HTMLInputElement>) {
    const files = Array.from(e.target.files || []).map(f => f.name)
    setVideos(files)
  }

  async function handleSubmit(e: React.FormEvent) {
    e.preventDefault()
    if (type === 'standard' && videos.length > 0) {
      setError(t('rekoDocs.no_videos_standard'))
      return
    }
    if (!bookingId) return
    await createDoc(bookingId, { type, notes, photos, videos })
    navigate(`/reko/${bookingId}/docs`)
  }

  return (
    <form onSubmit={handleSubmit} className="space-y-4">
      {error && <p>{error}</p>}
      <div>
        <label className="block mb-1">{t('rekoDocs.type')}</label>
        <select
          value={type}
          onChange={e => setType(e.target.value as RekoDocType)}
          className="border p-2"
        >
          <option value="basis">basis</option>
          <option value="standard">standard</option>
          <option value="premium">premium</option>
        </select>
      </div>
      <div>
        <label className="block mb-1">{t('rekoDocs.notes')}</label>
        <textarea
          value={notes}
          onChange={e => setNotes(e.target.value)}
          className="border p-2 w-full"
        />
      </div>
      {(type === 'standard' || type === 'premium') && (
        <div>
          <label className="block mb-1">{t('rekoDocs.photos')}</label>
          <input
            type="file"
            multiple
            accept="image/*"
            onChange={handlePhotoChange}
            data-testid="photo-input"
          />
        </div>
      )}
      {type === 'premium' && (
        <div>
          <label className="block mb-1">{t('rekoDocs.videos')}</label>
          <input
            type="file"
            multiple
            accept="video/*"
            onChange={handleVideoChange}
            data-testid="video-input"
          />
        </div>
      )}
      <button className="bg-blue-500 text-white px-4 py-2">{t('rekoDocs.save')}</button>
    </form>
  )
}

export default RekoDocForm
