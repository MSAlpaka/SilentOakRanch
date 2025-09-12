import { describe, it, expect } from 'vitest'
import i18n from '../../i18n'
import { t } from 'i18next'

describe('appointment subject translations', () => {
  it.each(['en', 'de'])('returns values for %s', async (lang) => {
    await i18n.changeLanguage(lang)
    expect(t('appointment.confirmation.subject')).not.toBe('appointment.confirmation.subject')
    expect(t('appointment.reminder.subject')).not.toBe('appointment.reminder.subject')
    expect(t('appointment.cancellation.subject')).not.toBe('appointment.cancellation.subject')
  })
})
