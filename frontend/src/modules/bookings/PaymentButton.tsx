import { loadStripe, Stripe } from '@stripe/stripe-js'
import api from '../../axios'

const publishableKey = import.meta.env.VITE_STRIPE_PUBLISHABLE_KEY

const stripePromise: Promise<Stripe | null> = publishableKey
  ? loadStripe(publishableKey)
  : Promise.resolve<Stripe | null>(null)

type PayResponse = {
  sessionId: string
}

interface PaymentButtonProps {
  bookingId: number
  disabled?: boolean
}

function PaymentButton({ bookingId, disabled = false }: PaymentButtonProps) {
  const isStripeConfigured = Boolean(publishableKey)
  const buttonDisabled = disabled || !isStripeConfigured
  const buttonTitle = disabled
    ? 'Payment not available'
    : !isStripeConfigured
      ? 'Stripe publishable key missing'
      : undefined

  async function handlePay() {
    if (!Number.isFinite(bookingId)) {
      console.error('Booking ID is required to start the payment process.')
      return
    }

    if (buttonDisabled) {
      console.warn('Payment cannot be initiated while the button is disabled.')
      return
    }

    const stripe = await stripePromise
    if (!stripe) {
      console.error('Stripe publishable key is not configured.')
      return
    }

    try {
      const origin = window.location.origin
      const response = await api.post<PayResponse>(`/bookings/${bookingId}/pay`, {
        successUrl: `${origin}/bookings?payment=success`,
        cancelUrl: `${origin}/bookings?payment=cancel`,
      })

      const { sessionId } = response.data
      if (!sessionId) {
        throw new Error('Missing session ID from payment endpoint response.')
      }

      const { error } = await stripe.redirectToCheckout({ sessionId })
      if (error) {
        console.error('Stripe Checkout redirect failed.', error)
      }
    } catch (error) {
      console.error('Unable to start the Stripe Checkout session.', error)
    }
  }

  return (
    <button
      onClick={handlePay}
      className="bg-green-500 text-white px-4 py-2"
      disabled={buttonDisabled}
      title={buttonTitle}
    >
      Pay
    </button>
  )
}

export default PaymentButton
