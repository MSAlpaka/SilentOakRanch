import { useMemo } from 'react'
import { SubscriptionData } from './types'

export default function useMockData(): SubscriptionData[] {
  return useMemo(
    () => [
      {
        id: 1,
        type: 'STALL',
        title: 'Box 1',
        amount: 300,
        startsAt: '2024-01-01',
        autoRenew: true,
      },
      {
        id: 2,
        type: 'HORSE',
        title: 'Hufpflege',
        amount: 50,
        startsAt: '2024-02-01',
        autoRenew: false,
        endDate: '2024-08-01',
      },
    ],
    []
  )
}
