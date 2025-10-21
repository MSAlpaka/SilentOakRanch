export interface Booking {
  uuid: string;
  resource: 'solekammer' | 'waage' | 'schmied';
  name: string;
  horseName?: string;
  slotStart: string;
  slotEnd: string;
  status: 'pending' | 'paid' | 'confirmed' | 'completed' | 'cancelled';
  price: number;
  email?: string;
  phone?: string;
}

export type BookingStatus = Booking['status'];

export interface BookingQueryParams {
  since?: string;
  resource?: Booking['resource'];
  status?: BookingStatus;
}
