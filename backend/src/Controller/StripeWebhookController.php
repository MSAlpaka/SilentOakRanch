<?php

namespace App\Controller;

use App\Repository\BookingRepository;
use App\Service\StripeService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Contracts\Translation\TranslatorInterface;

class StripeWebhookController extends AbstractController
{
    public function __construct(private TranslatorInterface $translator)
    {
    }
    #[Route('/api/bookings/{id}/pay', name: 'api_booking_pay', methods: ['POST'])]
    public function pay(int $id, BookingRepository $bookingRepository, StripeService $stripeService): JsonResponse
    {
        $booking = $bookingRepository->find($id);
        if (!$booking) {
            return $this->json(['message' => $this->translator->trans('Booking not found', [], 'validators')], 404);
        }

        $amount = (int) (floatval($booking->getPrice()) * 100);
        $intent = $stripeService->createPaymentIntent($amount, 'usd', ['booking_id' => $id]);

        return $this->json(['clientSecret' => $intent->client_secret]);
    }

    #[Route('/api/stripe/webhook', name: 'api_stripe_webhook', methods: ['POST'])]
    public function webhook(Request $request): JsonResponse
    {
        // Handle webhook events as needed
        return $this->json(['received' => true]);
    }
}
