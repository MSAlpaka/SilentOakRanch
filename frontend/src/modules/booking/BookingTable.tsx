import { useMemo, useState } from 'react';
import type { Booking, BookingStatus } from './types';

interface BookingTableProps {
  bookings: Booking[];
  loading?: boolean;
  onView: (booking: Booking) => void;
  onUpdateStatus: (booking: Booking, status: BookingStatus) => Promise<void> | void;
}

const statusStyles: Record<BookingStatus, string> = {
  pending: 'bg-yellow-50 text-yellow-800 border-yellow-200',
  paid: 'bg-blue-50 text-blue-800 border-blue-200',
  confirmed: 'bg-green-50 text-green-800 border-green-200',
  completed: 'bg-gray-100 text-gray-600 border-gray-200',
  cancelled: 'bg-red-50 text-red-700 border-red-200',
};

function formatDateRange(start: string, end: string): string {
  const startDate = new Date(start);
  const endDate = new Date(end);
  return `${startDate.toLocaleDateString(undefined, {
    weekday: 'short',
    month: 'short',
    day: 'numeric',
  })} · ${startDate.toLocaleTimeString(undefined, {
    hour: '2-digit',
    minute: '2-digit',
  })} - ${endDate.toLocaleTimeString(undefined, {
    hour: '2-digit',
    minute: '2-digit',
  })}`;
}

const BookingTable = ({ bookings, loading = false, onView, onUpdateStatus }: BookingTableProps): JSX.Element => {
  const [sortDirection, setSortDirection] = useState<'asc' | 'desc'>('asc');
  const [updatingId, setUpdatingId] = useState<string | null>(null);

  const sortedBookings = useMemo(() => {
    const sorted = [...bookings];
    sorted.sort((a, b) => {
      const aDate = new Date(a.slotStart).getTime();
      const bDate = new Date(b.slotStart).getTime();
      return sortDirection === 'asc' ? aDate - bDate : bDate - aDate;
    });
    return sorted;
  }, [bookings, sortDirection]);

  const handleSortToggle = () => {
    setSortDirection((prev) => (prev === 'asc' ? 'desc' : 'asc'));
  };

  const handleAction = async (booking: Booking, status: BookingStatus) => {
    try {
      setUpdatingId(booking.uuid);
      await onUpdateStatus(booking, status);
    } finally {
      setUpdatingId(null);
    }
  };

  const renderStatusPill = (status: BookingStatus) => (
    <span className={`inline-flex items-center gap-1 rounded-full border px-3 py-1 text-xs font-semibold ${statusStyles[status]}`}>
      {status}
    </span>
  );

  return (
    <div className="flex flex-col gap-4">
      <div className="flex items-center justify-between gap-3">
        <h2 className="text-lg font-semibold">Bookings</h2>
        <button
          type="button"
          onClick={handleSortToggle}
          className="rounded-full border border-[#385a3f] px-3 py-1 text-xs font-medium text-[#385a3f] transition hover:bg-[#e1ded5]"
        >
          Sort by date {sortDirection === 'asc' ? '↑' : '↓'}
        </button>
      </div>
      {loading ? <p className="text-sm text-[#385a3f]">Loading bookings…</p> : null}
      <div className="hidden overflow-hidden rounded-xl border border-[#e1ded5] md:block">
        <table className="min-w-full divide-y divide-[#e1ded5]">
          <thead className="bg-[#f1ede4] text-left text-xs uppercase tracking-wider text-[#385a3f]">
            <tr>
              <th className="px-4 py-3">Name</th>
              <th className="px-4 py-3">Horse</th>
              <th className="px-4 py-3">Resource</th>
              <th className="px-4 py-3">Date / Time</th>
              <th className="px-4 py-3">Status</th>
              <th className="px-4 py-3 text-right">Price</th>
              <th className="px-4 py-3">Actions</th>
            </tr>
          </thead>
          <tbody className="divide-y divide-[#f1ede4] bg-white text-sm">
            {sortedBookings.map((booking) => (
              <tr key={booking.uuid} className="transition hover:bg-[#f7f4ee]">
                <td className="px-4 py-3 font-medium">{booking.name}</td>
                <td className="px-4 py-3">{booking.horseName ?? '—'}</td>
                <td className="px-4 py-3 capitalize">{booking.resource}</td>
                <td className="px-4 py-3 text-sm">{formatDateRange(booking.slotStart, booking.slotEnd)}</td>
                <td className="px-4 py-3">{renderStatusPill(booking.status)}</td>
                <td className="px-4 py-3 text-right font-semibold">{booking.price.toFixed(2)} €</td>
                <td className="px-4 py-3">
                  <div className="flex flex-wrap items-center gap-2">
                    <button
                      type="button"
                      onClick={() => void handleAction(booking, 'completed')}
                      disabled={updatingId === booking.uuid || booking.status === 'completed'}
                      className="rounded-lg bg-[#385a3f] px-3 py-1 text-xs font-semibold text-white transition hover:bg-[#2f4b34] disabled:cursor-not-allowed disabled:bg-gray-300"
                    >
                      Mark completed
                    </button>
                    <button
                      type="button"
                      onClick={() => void handleAction(booking, 'cancelled')}
                      disabled={updatingId === booking.uuid || booking.status === 'cancelled'}
                      className="rounded-lg border border-red-400 px-3 py-1 text-xs font-semibold text-red-600 transition hover:bg-red-50 disabled:cursor-not-allowed disabled:border-gray-300 disabled:text-gray-400"
                    >
                      Cancel
                    </button>
                    <button
                      type="button"
                      onClick={() => onView(booking)}
                      className="rounded-lg border border-[#385a3f] px-3 py-1 text-xs font-semibold text-[#385a3f] transition hover:bg-[#e1ded5]"
                    >
                      View
                    </button>
                  </div>
                </td>
              </tr>
            ))}
            {sortedBookings.length === 0 && !loading ? (
              <tr>
                <td colSpan={7} className="px-4 py-6 text-center text-sm text-[#385a3f]">
                  No bookings in this view.
                </td>
              </tr>
            ) : null}
          </tbody>
        </table>
      </div>
      <div className="flex flex-col gap-3 md:hidden">
        {sortedBookings.map((booking) => (
          <div
            key={booking.uuid}
            className={`rounded-xl border p-4 shadow-sm transition ${
              booking.status === 'confirmed'
                ? 'border-green-200 bg-green-50'
                : booking.status === 'pending'
                ? 'border-yellow-200 bg-yellow-50'
                : booking.status === 'completed'
                ? 'border-gray-200 bg-gray-100'
                : 'border-[#e1ded5] bg-white'
            }`}
          >
            <div className="flex items-center justify-between">
              <p className="text-base font-semibold">{booking.name}</p>
              {renderStatusPill(booking.status)}
            </div>
            <p className="text-sm text-[#385a3f]">{booking.horseName ?? '—'}</p>
            <p className="text-sm capitalize">{booking.resource}</p>
            <p className="text-sm text-[#385a3f]">{formatDateRange(booking.slotStart, booking.slotEnd)}</p>
            <p className="text-sm font-semibold">{booking.price.toFixed(2)} €</p>
            <div className="mt-3 flex flex-wrap gap-2">
              <button
                type="button"
                onClick={() => void handleAction(booking, 'completed')}
                disabled={updatingId === booking.uuid || booking.status === 'completed'}
                className="flex-1 rounded-lg bg-[#385a3f] px-3 py-2 text-xs font-semibold text-white transition hover:bg-[#2f4b34] disabled:cursor-not-allowed disabled:bg-gray-300"
              >
                Mark completed
              </button>
              <button
                type="button"
                onClick={() => void handleAction(booking, 'cancelled')}
                disabled={updatingId === booking.uuid || booking.status === 'cancelled'}
                className="flex-1 rounded-lg border border-red-400 px-3 py-2 text-xs font-semibold text-red-600 transition hover:bg-red-50 disabled:cursor-not-allowed disabled:border-gray-300 disabled:text-gray-400"
              >
                Cancel
              </button>
              <button
                type="button"
                onClick={() => onView(booking)}
                className="flex-1 rounded-lg border border-[#385a3f] px-3 py-2 text-xs font-semibold text-[#385a3f] transition hover:bg-[#e1ded5]"
              >
                View details
              </button>
            </div>
          </div>
        ))}
        {sortedBookings.length === 0 && !loading ? (
          <p className="rounded-xl border border-dashed border-[#e1ded5] p-6 text-center text-sm text-[#385a3f]">
            No bookings in this view.
          </p>
        ) : null}
      </div>
    </div>
  );
};

export default BookingTable;
