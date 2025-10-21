import { useCallback, useEffect, useMemo, useState } from 'react'
import { toast } from 'sonner'

import { useAuth } from '@/hooks/useAuth'
import { Button } from '@/components/ui/button'
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card'
import { Separator } from '@/components/ui/separator'
import { ApiError, getBookings, setAuthToken, updateStatus } from './api'
import type { Booking, BookingStatus } from './types'
import BookingTable from './BookingTable'
import BookingDetails from './BookingDetails'
import QrScanner from './QrScanner'

const RESOURCES: Array<{ label: string; value: 'all' | Booking['resource'] }> = [
  { label: 'All resources', value: 'all' },
  { label: 'Solekammer', value: 'solekammer' },
  { label: 'Waage', value: 'waage' },
  { label: 'Schmied', value: 'schmied' },
]

function startOfDayIso(date: Date): string {
  const copy = new Date(date)
  copy.setHours(0, 0, 0, 0)
  return copy.toISOString()
}

const REFRESH_INTERVAL = 60_000

const BookingDashboard = (): JSX.Element => {
  const { token } = useAuth()
  const [bookings, setBookings] = useState<Booking[]>([])
  const [selectedResource, setSelectedResource] = useState<(typeof RESOURCES)[number]['value']>('all')
  const [loading, setLoading] = useState(false)
  const [error, setError] = useState<string | null>(null)
  const [selectedBooking, setSelectedBooking] = useState<Booking | null>(null)

  useEffect(() => {
    setAuthToken(token ?? null)
    return () => {
      setAuthToken(null)
    }
  }, [token])

  const applyUpdatedBooking = useCallback((updated: Booking) => {
    setBookings((prev) => {
      const exists = prev.some((item) => item.uuid === updated.uuid)
      if (exists) {
        return prev.map((item) => (item.uuid === updated.uuid ? updated : item))
      }
      return [updated, ...prev].sort(
        (a, b) => new Date(a.slotStart).getTime() - new Date(b.slotStart).getTime(),
      )
    })
    setSelectedBooking((prev) => (prev && prev.uuid === updated.uuid ? updated : prev))
  }, [])

  const fetchBookings = useCallback(async () => {
    try {
      setLoading(true)
      setError(null)
      const since = startOfDayIso(new Date(Date.now() - 7 * 24 * 60 * 60 * 1000))
      const data = await getBookings({ since, resource: selectedResource === 'all' ? undefined : selectedResource })
      setBookings(data)
    } catch (err) {
      if (err instanceof ApiError && err.status === 401) {
        window.location.href = '/login'
        return
      }
      const message = err instanceof Error ? err.message : 'Unable to load bookings.'
      setError(message)
      toast.error(message)
    } finally {
      setLoading(false)
    }
  }, [selectedResource])

  useEffect(() => {
    void fetchBookings()
  }, [fetchBookings])

  useEffect(() => {
    const timer = setInterval(() => {
      void fetchBookings()
    }, REFRESH_INTERVAL)

    return () => clearInterval(timer)
  }, [fetchBookings])

  const handleStatusChange = useCallback(
    async (booking: Booking, status: BookingStatus) => {
      try {
        const updated = await updateStatus(booking.uuid, status)
        applyUpdatedBooking(updated)
        toast.success(`Booking updated to ${status}.`)
      } catch (err) {
        if (err instanceof ApiError && err.status === 401) {
          window.location.href = '/login'
          return
        }
        const message = err instanceof Error ? err.message : 'Unable to update booking status.'
        setError(message)
        toast.error(message)
      }
    },
    [applyUpdatedBooking],
  )

  const filteredBookings = useMemo(() => {
    if (selectedResource === 'all') {
      return bookings
    }
    return bookings.filter((booking) => booking.resource === selectedResource)
  }, [bookings, selectedResource])

  const stats = useMemo(() => {
    const today = new Date()
    const startOfToday = new Date(today)
    startOfToday.setHours(0, 0, 0, 0)
    const startOfWeek = new Date(today)
    startOfWeek.setDate(today.getDate() - 6)
    startOfWeek.setHours(0, 0, 0, 0)

    const totals = {
      today: 0,
      week: 0,
      pending: 0,
      paid: 0,
      completed: 0,
    }

    bookings.forEach((booking) => {
      const start = new Date(booking.slotStart)
      if (start >= startOfToday && start <= today) {
        totals.today += 1
      }
      if (start >= startOfWeek && start <= today) {
        totals.week += 1
      }
      if (booking.status === 'pending') {
        totals.pending += 1
      }
      if (booking.status === 'paid' || booking.status === 'confirmed') {
        totals.paid += 1
      }
      if (booking.status === 'completed') {
        totals.completed += 1
      }
    })

    return totals
  }, [bookings])

  return (
    <div className="flex flex-col gap-6">
      <header className="space-y-2">
        <h1 className="text-3xl font-semibold text-primary">Booking dashboard</h1>
        <p className="text-sm text-primary/80">
          Monitor upcoming sessions, confirm arrivals, and keep Silent Oak Ranch on schedule.
        </p>
      </header>

      <div className="flex flex-wrap gap-2">
        {RESOURCES.map((resource) => (
          <Button
            key={resource.value}
            variant={selectedResource === resource.value ? 'default' : 'outline'}
            onClick={() => setSelectedResource(resource.value)}
            className={selectedResource === resource.value ? 'shadow-lg' : ''}
          >
            {resource.label}
          </Button>
        ))}
      </div>

      {error ? (
        <Card className="border-red-300 bg-red-50 text-red-700">
          <CardHeader className="p-4">
            <CardTitle className="text-base font-medium text-red-800">{error}</CardTitle>
          </CardHeader>
        </Card>
      ) : null}

      <section className="grid gap-4 sm:grid-cols-2 lg:grid-cols-5">
        <Card>
          <CardHeader>
            <CardTitle>Today</CardTitle>
          </CardHeader>
          <CardContent className="pt-0 text-2xl font-semibold text-[#222]">{stats.today}</CardContent>
        </Card>
        <Card>
          <CardHeader>
            <CardTitle>This week</CardTitle>
          </CardHeader>
          <CardContent className="pt-0 text-2xl font-semibold text-[#222]">{stats.week}</CardContent>
        </Card>
        <Card>
          <CardHeader>
            <CardTitle>Pending</CardTitle>
          </CardHeader>
          <CardContent className="pt-0 text-2xl font-semibold text-[#222]">{stats.pending}</CardContent>
        </Card>
        <Card>
          <CardHeader>
            <CardTitle>Paid / Confirmed</CardTitle>
          </CardHeader>
          <CardContent className="pt-0 text-2xl font-semibold text-[#222]">{stats.paid}</CardContent>
        </Card>
        <Card>
          <CardHeader>
            <CardTitle>Completed</CardTitle>
          </CardHeader>
          <CardContent className="pt-0 text-2xl font-semibold text-[#222]">{stats.completed}</CardContent>
        </Card>
      </section>

      <div className="flex flex-col gap-4 lg:flex-row">
        <Card className="flex-1">
          <CardHeader className="pb-4">
            <div className="flex items-center justify-between">
              <CardTitle className="text-xl text-[#222]">Upcoming bookings</CardTitle>
              <Separator className="hidden lg:block lg:w-32" />
            </div>
          </CardHeader>
          <CardContent className="pt-0">
            <BookingTable
              bookings={filteredBookings}
              loading={loading}
              onView={setSelectedBooking}
              onUpdateStatus={handleStatusChange}
            />
          </CardContent>
        </Card>
        <Card className="w-full lg:w-80 xl:w-96">
          <CardHeader className="pb-4">
            <CardTitle className="text-xl text-[#222]">Check-in</CardTitle>
          </CardHeader>
          <CardContent className="space-y-4 pt-0">
            <QrScanner
              onSuccess={(updated) => {
                applyUpdatedBooking(updated)
                toast.success('Booking marked as completed.')
              }}
              onManualComplete={async (uuid) => {
                const updated = await updateStatus(uuid, 'completed')
                applyUpdatedBooking(updated)
                toast.success('Booking marked as completed.')
                return updated
              }}
              onRefresh={() => void fetchBookings()}
            />
          </CardContent>
        </Card>
      </div>

      <BookingDetails booking={selectedBooking} onClose={() => setSelectedBooking(null)} />
    </div>
  )
}

export default BookingDashboard
