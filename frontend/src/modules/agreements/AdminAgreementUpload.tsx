import { useState } from 'react'
import { uploadAgreement } from '../../api/agreements'
import { useTranslation } from 'react-i18next'

function AdminAgreementUpload() {
  const { t } = useTranslation()
  const [type, setType] = useState('TOS')
  const [version, setVersion] = useState('')
  const [file, setFile] = useState<File | null>(null)

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault()
    if (!file) return
    await uploadAgreement({ type, version, file })
  }

  return (
    <form onSubmit={handleSubmit} className="p-4 space-y-4">
      <div>
        <label className="block mb-1">{t('agreements.type')}</label>
        <select
          aria-label={t('agreements.type')}
          value={type}
          onChange={e => setType(e.target.value)}
          className="border p-2 w-full"
        >
          <option value="TOS">TOS</option>
          <option value="Privacy">Privacy</option>
        </select>
      </div>
      <div>
        <label className="block mb-1">{t('agreements.version')}</label>
        <input
          aria-label={t('agreements.version')}
          className="border p-2 w-full"
          value={version}
          onChange={e => setVersion(e.target.value)}
        />
      </div>
      <div>
        <label className="block mb-1">{t('agreements.file')}</label>
        <input
          aria-label={t('agreements.file')}
          type="file"
          onChange={e => setFile(e.target.files ? e.target.files[0] : null)}
        />
      </div>
      <button type="submit" className="bg-blue-500 text-white px-4 py-2">
        {t('agreements.upload')}
      </button>
    </form>
  )
}

export default AdminAgreementUpload
