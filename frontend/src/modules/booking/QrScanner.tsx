import { useEffect, useMemo, useRef, useState } from 'react'
import { toast } from 'sonner'

import { ApiError, checkQr } from './api'
import type { Booking } from './types'

interface QrScannerProps {
  onSuccess: (booking: Booking) => void
  onManualComplete: (uuid: string) => Promise<Booking>
  onRefresh?: () => void
}

type ScannerStatus = 'idle' | 'success' | 'error'

const QrScanner = ({ onSuccess, onManualComplete, onRefresh }: QrScannerProps): JSX.Element => {
  const containerId = useMemo(() => `qr-scanner-${Math.random().toString(36).slice(2, 9)}`, [])
  const scannerRef = useRef<any>(null)
  const onSuccessRef = useRef(onSuccess)
  const onRefreshRef = useRef(onRefresh)
  const [status, setStatus] = useState<ScannerStatus>('idle')
  const [message, setMessage] = useState<string>('Point the camera at a booking QR code')
  const [manualUuid, setManualUuid] = useState('')
  const [manualLoading, setManualLoading] = useState(false)
  const [cameraError, setCameraError] = useState<string | null>(null)

  useEffect(() => {
    onSuccessRef.current = onSuccess
  }, [onSuccess])

  useEffect(() => {
    onRefreshRef.current = onRefresh
  }, [onRefresh])

  useEffect(() => {
    let isMounted = true

    async function initScanner() {
      if (typeof window === 'undefined') {
        return
      }

      try {
        const { Html5Qrcode } = await import('html5-qrcode')
        if (!isMounted) {
          return
        }
        const scanner = new Html5Qrcode(containerId)
        scannerRef.current = scanner

        await scanner.start(
          { facingMode: 'environment' },
          {
            fps: 10,
            qrbox: { width: 250, height: 250 },
          },
          async (decodedText: string) => {
            if (!decodedText) {
              return
            }
            scanner.pause?.(true)
            try {
              const booking = await checkQr(decodedText)
              setStatus('success')
              setMessage('✅ Check-in erfolgreich')
              onSuccessRef.current(booking)
              onRefreshRef.current?.()
            } catch (error) {
              if (error instanceof ApiError && error.status === 401) {
                window.location.href = '/login'
                return
              }
              setStatus('error')
              const errMessage = error instanceof Error ? error.message : 'Invalid QR code'
              setMessage(`❌ ${errMessage}`)
              toast.error(errMessage)
            } finally {
              setTimeout(() => {
                setStatus('idle')
                setMessage('Point the camera at a booking QR code')
                try {
                  scanner.resume?.()
                } catch (resumeError) {
                  // ignore resume errors
                }
              }, 2500)
            }
          },
          () => {
            // ignore scan failure callbacks to avoid noise
          },
        )
      } catch (error) {
        const errMessage =
          error instanceof Error
            ? error.message
            : 'Unable to initialise QR scanner. Please check camera permissions.'
        if (isMounted) {
          setCameraError(errMessage)
          toast.error(errMessage)
        }
      }
    }

    void initScanner()

    return () => {
      isMounted = false
      if (scannerRef.current) {
        const instance = scannerRef.current
        instance
          .stop()
          .catch(() => undefined)
          .finally(() => {
            instance.clear()
          })
      }
    }
  }, [containerId])

  const handleManualSubmit = async (event: React.FormEvent<HTMLFormElement>) => {
    event.preventDefault()
    if (!manualUuid.trim()) {
      return
    }
    setManualLoading(true)
    setCameraError(null)
    try {
      const booking = await onManualComplete(manualUuid.trim())
      setStatus('success')
      setMessage('✅ Check-in erfolgreich')
      onSuccess(booking)
      onRefresh?.()
      setManualUuid('')
    } catch (error) {
      if (error instanceof ApiError && error.status === 401) {
        window.location.href = '/login'
        return
      }
      setStatus('error')
      const errMessage = error instanceof Error ? error.message : 'Unable to update booking.'
      setMessage(`❌ ${errMessage}`)
      toast.error(errMessage)
    } finally {
      setManualLoading(false)
      setTimeout(() => {
        setStatus('idle')
        setMessage('Point the camera at a booking QR code')
      }, 2500)
    }
  }

  return (
    <div className="flex flex-col gap-4 text-[#222]">
      <h2 className="text-lg font-semibold">QR check-in</h2>
      {cameraError ? (
        <p className="rounded-lg border border-red-200 bg-red-50 p-3 text-sm text-red-700">{cameraError}</p>
      ) : null}
      <div className="flex flex-col items-center gap-3">
        <div className="flex h-64 w-full max-w-xs items-center justify-center overflow-hidden rounded-2xl border-4 border-dashed border-[#385a3f] bg-[#f7f4ee]">
          <div id={containerId} className="h-full w-full" />
        </div>
        <p className={`text-center text-sm ${status === 'success' ? 'text-green-700' : status === 'error' ? 'text-red-700' : 'text-[#385a3f]'}`}>
          {message}
        </p>
      </div>
      <form className="flex flex-col gap-2" onSubmit={handleManualSubmit}>
        <label htmlFor="manual-uuid" className="text-sm font-medium text-[#385a3f]">
          Manual booking code
        </label>
        <div className="flex flex-col gap-2 sm:flex-row">
          <input
            id="manual-uuid"
            value={manualUuid}
            onChange={(event) => setManualUuid(event.target.value)}
            placeholder="Paste booking UUID"
            className="w-full rounded-lg border border-[#e1ded5] bg-white px-3 py-2 text-sm focus:border-[#385a3f] focus:outline-none focus:ring-2 focus:ring-[#385a3f]/40"
          />
          <button
            type="submit"
            disabled={manualLoading}
            className="rounded-lg bg-[#385a3f] px-4 py-2 text-sm font-semibold text-white transition hover:bg-[#2f4b34] disabled:cursor-not-allowed disabled:bg-gray-300"
          >
            Mark completed
          </button>
        </div>
      </form>
    </div>
  )
}

export default QrScanner
