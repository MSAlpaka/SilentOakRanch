import type { Booking, BookingQueryParams, BookingStatus } from './types';

const API_BASE_URL = import.meta.env.VITE_RANCH_API_URL as string | undefined;
const API_KEY = import.meta.env.VITE_RANCH_API_KEY as string | undefined;

export class ApiError extends Error {
  public status?: number;

  constructor(message: string, status?: number) {
    super(message);
    this.name = 'ApiError';
    this.status = status;
  }
}

async function request<T>(path: string, options: RequestInit = {}): Promise<T> {
  if (!API_BASE_URL) {
    throw new ApiError('Ranch API URL is not configured.');
  }

  if (!API_KEY) {
    throw new ApiError('Ranch API key is not configured.');
  }

  const headers: HeadersInit = {
    'Content-Type': 'application/json',
    Authorization: `Bearer ${API_KEY}`,
    ...(options.headers ?? {}),
  };

  const response = await fetch(`${API_BASE_URL.replace(/\/$/, '')}${path}`, {
    ...options,
    headers,
  });

  if (response.status === 401) {
    throw new ApiError('Unauthorized request. Please log in again.', 401);
  }

  if (!response.ok) {
    let message = 'Failed to communicate with the Silent Oak Ranch API.';
    try {
      const body = await response.json();
      if (body?.message) {
        message = body.message;
      }
    } catch (error) {
      // ignore json parse errors and use fallback message
    }
    throw new ApiError(message, response.status);
  }

  if (response.status === 204) {
    return undefined as T;
  }

  return response.json() as Promise<T>;
}

export async function getBookings(params: BookingQueryParams = {}): Promise<Booking[]> {
  const searchParams = new URLSearchParams();
  if (params.since) {
    searchParams.set('since', params.since);
  }
  if (params.resource) {
    searchParams.set('resource', params.resource);
  }
  if (params.status) {
    searchParams.set('status', params.status);
  }

  const query = searchParams.toString();
  const path = `/bookings${query ? `?${query}` : ''}`;
  return request<Booking[]>(path, {
    method: 'GET',
  });
}

export async function updateStatus(uuid: string, status: BookingStatus): Promise<Booking> {
  return request<Booking>(`/bookings/${uuid}/status`, {
    method: 'PATCH',
    body: JSON.stringify({ status }),
  });
}

export async function checkQr(payload: string): Promise<Booking> {
  const parts = payload.split('|');
  if (parts.length < 3 || parts[0] !== 'SOR') {
    throw new ApiError('Invalid QR payload.');
  }

  const uuid = parts[2];

  return request<Booking>(`/bookings/${uuid}/status`, {
    method: 'PATCH',
    body: JSON.stringify({ status: 'completed', payload }),
  });
}
