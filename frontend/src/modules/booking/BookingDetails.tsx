import { useMemo, useState } from 'react';
import type { Booking } from './types';

interface BookingDetailsProps {
  booking: Booking | null;
  onClose: () => void;
}

const BookingDetails = ({ booking, onClose }: BookingDetailsProps): JSX.Element | null => {
  const [copyMessage, setCopyMessage] = useState<string | null>(null);

  const qrUrl = useMemo(() => {
    if (!booking) {
      return '';
    }
    return `https://silent-oak-ranch.de/wp-json/sor/v1/qr?ref=${booking.uuid}`;
  }, [booking]);

  if (!booking) {
    return null;
  }

  const handleCopy = async () => {
    try {
      await navigator.clipboard.writeText(qrUrl);
      setCopyMessage('Link copied to clipboard');
      setTimeout(() => setCopyMessage(null), 2000);
    } catch (error) {
      setCopyMessage('Unable to copy link');
      setTimeout(() => setCopyMessage(null), 2000);
    }
  };

  return (
    <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4">
      <div className="w-full max-w-lg rounded-2xl bg-white p-6 shadow-lg">
        <div className="flex items-start justify-between gap-4">
          <div>
            <h2 className="text-2xl font-semibold text-[#222]">{booking.name}</h2>
            <p className="text-sm text-[#385a3f]">{booking.resource.toUpperCase()} · {booking.uuid}</p>
          </div>
          <button
            type="button"
            onClick={onClose}
            className="rounded-full border border-transparent bg-[#f7f4ee] px-3 py-1 text-sm font-semibold text-[#385a3f] transition hover:bg-[#e1ded5]"
          >
            Close
          </button>
        </div>

        <div className="mt-4 grid gap-4 sm:grid-cols-2">
          <div className="space-y-2">
            <p className="text-sm text-[#385a3f]">Horse</p>
            <p className="text-base font-medium">{booking.horseName ?? '—'}</p>

            <p className="text-sm text-[#385a3f]">Session</p>
            <p className="text-base font-medium">
              {new Date(booking.slotStart).toLocaleString()} – {new Date(booking.slotEnd).toLocaleTimeString()}
            </p>

            <p className="text-sm text-[#385a3f]">Price</p>
            <p className="text-base font-medium">{booking.price.toFixed(2)} €</p>

            {booking.email ? (
              <>
                <p className="text-sm text-[#385a3f]">Email</p>
                <a href={`mailto:${booking.email}`} className="text-base font-medium text-[#385a3f] underline">
                  {booking.email}
                </a>
              </>
            ) : null}

            {booking.phone ? (
              <>
                <p className="text-sm text-[#385a3f]">Phone</p>
                <a href={`tel:${booking.phone}`} className="text-base font-medium text-[#385a3f] underline">
                  {booking.phone}
                </a>
              </>
            ) : null}
          </div>
          <div className="flex flex-col items-center justify-center gap-3 rounded-xl bg-[#f7f4ee] p-4">
            <img src={qrUrl} alt="Booking QR code" className="h-48 w-48 rounded-lg border border-[#e1ded5] bg-white object-contain p-2" />
            <button
              type="button"
              onClick={handleCopy}
              className="w-full rounded-lg bg-[#385a3f] px-4 py-2 text-sm font-semibold text-white transition hover:bg-[#2f4b34]"
            >
              Copy link to client
            </button>
            {copyMessage ? <p className="text-xs text-[#385a3f]">{copyMessage}</p> : null}
          </div>
        </div>
      </div>
    </div>
  );
};

export default BookingDetails;
