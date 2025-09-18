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
    public function pay(
        int $id,
        Request $request,
        BookingRepository $bookingRepository,
        StripeService $stripeService
    ): JsonResponse {
        $booking = $bookingRepository->find($id);
        if (!$booking) {
            return $this->json(['message' => $this->translator->trans('Booking not found', [], 'validators')], 404);
        }

        try {
            $payload = $request->toArray();
        } catch (\JsonException) {
            return $this->json(['message' => $this->translator->trans('Invalid payload', [], 'validators')], 400);
        }

        $successUrl = $payload['successUrl'] ?? null;
        $cancelUrl = $payload['cancelUrl'] ?? null;

        if (!is_string($successUrl) || !filter_var($successUrl, FILTER_VALIDATE_URL)) {
            return $this->json(['message' => $this->translator->trans('Invalid payload', [], 'validators')], 400);
        }

        if (!is_string($cancelUrl) || !filter_var($cancelUrl, FILTER_VALIDATE_URL)) {
            return $this->json(['message' => $this->translator->trans('Invalid payload', [], 'validators')], 400);
        }

        $amount = (int) round(((float) $booking->getPrice()) * 100);
        if ($amount <= 0) {
            return $this->json(['message' => $this->translator->trans('Invalid booking price', [], 'validators')], 400);
        }

        $session = $stripeService->createCheckoutSession(
            $amount,
            'usd',
            $successUrl,
            $cancelUrl,
            $booking->getLabel(),
            ['booking_id' => (string) $booking->getId()],
            $booking->getUser()
        );

        return $this->json(['sessionId' => $session->id]);
    }

    #[Route('/api/stripe/webhook', name: 'api_stripe_webhook', methods: ['POST'])]
    public function webhook(Request $request): JsonResponse
    {
        // Handle webhook events as needed
        return $this->json(['received' => true]);
    }
}
