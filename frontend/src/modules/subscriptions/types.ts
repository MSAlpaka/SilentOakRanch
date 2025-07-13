export type SubscriptionData = {
  id?: number
  type: 'USER' | 'HORSE' | 'STALL'
  title: string
  amount: number
  startsAt: string
  autoRenew: boolean
  endDate?: string | null
}
