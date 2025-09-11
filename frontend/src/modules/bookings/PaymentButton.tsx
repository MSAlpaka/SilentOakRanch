import { loadStripe } from '@stripe/stripe-js'
import api from '../../axios'

const stripePromise = loadStripe('pk_test_12345')

function PaymentButton({ bookingId }: { bookingId: number }) {
  async function handlePay() {
    const stripe = await stripePromise
    if (!stripe) return
    const res = await api.post(`/bookings/${bookingId}/pay`)
    const { clientSecret } = res.data
    await stripe.redirectToCheckout({ sessionId: clientSecret })
  }

  return (
    <button onClick={handlePay} className="bg-green-500 text-white px-4 py-2">
      Pay
    </button>
  )
}

export default PaymentButton
