import { useCallback, useEffect, useMemo, useState } from 'react';
import { ApiError, getBookings, updateStatus } from './api';
import type { Booking, BookingStatus } from './types';
import BookingTable from './BookingTable';
import BookingDetails from './BookingDetails';
import QrScanner from './QrScanner';

const RESOURCES: Array<{ label: string; value: 'all' | Booking['resource'] }> = [
  { label: 'All resources', value: 'all' },
  { label: 'Solekammer', value: 'solekammer' },
  { label: 'Waage', value: 'waage' },
  { label: 'Schmied', value: 'schmied' },
];

function startOfDayIso(date: Date): string {
  const copy = new Date(date);
  copy.setHours(0, 0, 0, 0);
  return copy.toISOString();
}

const REFRESH_INTERVAL = 60_000;

const BookingDashboard = (): JSX.Element => {
  const [bookings, setBookings] = useState<Booking[]>([]);
  const [selectedResource, setSelectedResource] = useState<(typeof RESOURCES)[number]['value']>('all');
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const [selectedBooking, setSelectedBooking] = useState<Booking | null>(null);

  const applyUpdatedBooking = useCallback((updated: Booking) => {
    setBookings((prev) => {
      const exists = prev.some((item) => item.uuid === updated.uuid);
      if (exists) {
        return prev.map((item) => (item.uuid === updated.uuid ? updated : item));
      }
      return [updated, ...prev].sort(
        (a, b) => new Date(a.slotStart).getTime() - new Date(b.slotStart).getTime(),
      );
    });
    setSelectedBooking((prev) => (prev && prev.uuid === updated.uuid ? updated : prev));
  }, []);

  const fetchBookings = useCallback(async () => {
    try {
      setLoading(true);
      setError(null);
      const since = startOfDayIso(new Date(Date.now() - 7 * 24 * 60 * 60 * 1000));
      const data = await getBookings({ since, resource: selectedResource === 'all' ? undefined : selectedResource });
      setBookings(data);
    } catch (err) {
      if (err instanceof ApiError && err.status === 401) {
        window.location.href = '/login';
        return;
      }
      const message = err instanceof Error ? err.message : 'Unable to load bookings.';
      setError(message);
    } finally {
      setLoading(false);
    }
  }, [selectedResource]);

  useEffect(() => {
    void fetchBookings();
  }, [fetchBookings]);

  useEffect(() => {
    const timer = setInterval(() => {
      void fetchBookings();
    }, REFRESH_INTERVAL);

    return () => clearInterval(timer);
  }, [fetchBookings]);

  const handleStatusChange = useCallback(
    async (booking: Booking, status: BookingStatus) => {
      try {
        const updated = await updateStatus(booking.uuid, status);
        applyUpdatedBooking(updated);
      } catch (err) {
        if (err instanceof ApiError && err.status === 401) {
          window.location.href = '/login';
          return;
        }
        const message = err instanceof Error ? err.message : 'Unable to update booking status.';
        setError(message);
      }
    },
    [applyUpdatedBooking],
  );

  const filteredBookings = useMemo(() => {
    if (selectedResource === 'all') {
      return bookings;
    }
    return bookings.filter((booking) => booking.resource === selectedResource);
  }, [bookings, selectedResource]);

  const stats = useMemo(() => {
    const today = new Date();
    const startOfToday = new Date(today);
    startOfToday.setHours(0, 0, 0, 0);
    const startOfWeek = new Date(today);
    startOfWeek.setDate(today.getDate() - 6);
    startOfWeek.setHours(0, 0, 0, 0);

    const totals = {
      today: 0,
      week: 0,
      pending: 0,
      paid: 0,
      completed: 0,
    };

    bookings.forEach((booking) => {
      const start = new Date(booking.slotStart);
      if (start >= startOfToday && start <= today) {
        totals.today += 1;
      }
      if (start >= startOfWeek && start <= today) {
        totals.week += 1;
      }
      if (booking.status === 'pending') {
        totals.pending += 1;
      }
      if (booking.status === 'paid' || booking.status === 'confirmed') {
        totals.paid += 1;
      }
      if (booking.status === 'completed') {
        totals.completed += 1;
      }
    });

    return totals;
  }, [bookings]);

  return (
    <div className="min-h-screen bg-[#f7f4ee] p-6 text-[#222]">
      <div className="mx-auto flex w-full max-w-7xl flex-col gap-6">
        <header className="flex flex-col gap-2">
          <h1 className="text-3xl font-semibold">Booking dashboard</h1>
          <p className="text-sm text-[#385a3f]">
            Monitor upcoming sessions, confirm arrivals, and keep Silent Oak Ranch on schedule.
          </p>
        </header>

        <nav className="flex flex-wrap gap-2">
          {RESOURCES.map((resource) => (
            <button
              key={resource.value}
              type="button"
              onClick={() => setSelectedResource(resource.value)}
              className={`rounded-full border px-4 py-2 text-sm font-medium transition-colors focus:outline-none focus:ring-2 focus:ring-[#385a3f] focus:ring-offset-2 ${
                selectedResource === resource.value
                  ? 'bg-[#385a3f] text-white shadow'
                  : 'bg-white text-[#385a3f] hover:bg-[#e1ded5]'
              }`}
            >
              {resource.label}
            </button>
          ))}
        </nav>

        {error ? (
          <div className="rounded-lg border border-red-300 bg-red-50 p-4 text-sm text-red-700">
            {error}
          </div>
        ) : null}

        <section className="grid gap-4 sm:grid-cols-2 lg:grid-cols-5">
          <article className="rounded-xl bg-white p-4 shadow-sm">
            <p className="text-sm text-[#385a3f]">Today</p>
            <p className="text-2xl font-semibold">{stats.today}</p>
          </article>
          <article className="rounded-xl bg-white p-4 shadow-sm">
            <p className="text-sm text-[#385a3f]">This week</p>
            <p className="text-2xl font-semibold">{stats.week}</p>
          </article>
          <article className="rounded-xl bg-white p-4 shadow-sm">
            <p className="text-sm text-[#385a3f]">Pending</p>
            <p className="text-2xl font-semibold">{stats.pending}</p>
          </article>
          <article className="rounded-xl bg-white p-4 shadow-sm">
            <p className="text-sm text-[#385a3f]">Paid / Confirmed</p>
            <p className="text-2xl font-semibold">{stats.paid}</p>
          </article>
          <article className="rounded-xl bg-white p-4 shadow-sm">
            <p className="text-sm text-[#385a3f]">Completed</p>
            <p className="text-2xl font-semibold">{stats.completed}</p>
          </article>
        </section>

        <div className="flex flex-col gap-4 lg:flex-row">
          <div className="flex-1">
            <div className="rounded-xl bg-white p-4 shadow-sm">
              <BookingTable
                bookings={filteredBookings}
                loading={loading}
                onView={setSelectedBooking}
                onUpdateStatus={handleStatusChange}
              />
            </div>
          </div>
          <div className="w-full lg:w-80 xl:w-96">
            <div className="rounded-xl bg-white p-4 shadow-sm">
              <QrScanner
                onSuccess={(updated) => {
                  applyUpdatedBooking(updated);
                }}
                onManualComplete={async (uuid) => {
                  const updated = await updateStatus(uuid, 'completed');
                  applyUpdatedBooking(updated);
                  return updated;
                }}
                onRefresh={() => void fetchBookings()}
              />
            </div>
          </div>
        </div>
      </div>
      <BookingDetails booking={selectedBooking} onClose={() => setSelectedBooking(null)} />
    </div>
  );
};

export default BookingDashboard;
