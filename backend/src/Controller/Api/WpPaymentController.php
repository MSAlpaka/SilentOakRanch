<?php

namespace App\Controller\Api;

use App\Controller\Api\Dto\WpPaymentConfirmationRequest;
use App\Entity\Booking;
use App\Enum\BookingStatus;
use App\Message\ContractQueued;
use App\Message\WpPaymentConfirmed;
use App\Repository\BookingRepository;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Validator\ConstraintViolationInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;

class WpPaymentController extends AbstractController
{
    public function __construct(
        private readonly BookingRepository $bookings,
        private readonly ValidatorInterface $validator,
        private readonly EntityManagerInterface $entityManager,
        private readonly MessageBusInterface $bus,
        private readonly LoggerInterface $logger
    ) {
    }

    #[Route('/api/wp/payments/confirm', methods: ['POST'])]
    public function confirm(Request $request): JsonResponse
    {
        $payload = json_decode($request->getContent(), true);
        if (!\is_array($payload)) {
            return $this->json(['ok' => false, 'error' => 'Invalid JSON payload.'], Response::HTTP_BAD_REQUEST);
        }

        $dto = WpPaymentConfirmationRequest::fromPayload($payload);
        $violations = $this->validator->validate($dto);
        if ($violations->count() > 0) {
            return $this->validationErrorResponse($violations);
        }

        /** @var Booking|null $booking */
        $booking = $this->bookings->find($dto->getBookingId());
        if (!$booking instanceof Booking) {
            return $this->json([
                'ok' => false,
                'error' => 'Booking not found.',
            ], Response::HTTP_NOT_FOUND);
        }

        try {
            $amount = $dto->getAmount();
        } catch (\InvalidArgumentException $exception) {
            return $this->json([
                'ok' => false,
                'error' => $exception->getMessage(),
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $previousStatus = $booking->getStatus();
        $newStatus = $this->mapStatus($dto->getStatus());

        if ($amount !== null) {
            $booking->setPrice($amount);
        }

        $booking->setStatus($newStatus);
        $booking->setIsConfirmed($newStatus === BookingStatus::CONFIRMED);

        $this->entityManager->flush();

        $this->logger->info('WordPress payment confirmation received', [
            'booking_id' => $booking->getId(),
            'previous_status' => $previousStatus->value,
            'new_status' => $newStatus->value,
            'payment_reference' => $dto->getPaymentReference(),
            'amount' => $amount,
        ]);

        $this->bus->dispatch(new WpPaymentConfirmed(
            $booking->getId(),
            $dto->getStatus(),
            $dto->getPaymentReference(),
            $amount,
            $dto->getRawPayload()
        ));

        if ($dto->getStatus() === 'paid') {
            $this->bus->dispatch(new ContractQueued($booking->getId(), 'payment:paid'));
        }

        return $this->json([
            'ok' => true,
            'id' => $booking->getId(),
            'status' => $booking->getStatus()->value,
        ]);
    }

    private function mapStatus(string $status): BookingStatus
    {
        return match ($status) {
            'cancelled' => BookingStatus::CANCELLED,
            'confirmed', 'completed', 'paid' => BookingStatus::CONFIRMED,
            default => BookingStatus::PENDING,
        };
    }

    private function validationErrorResponse(iterable $violations): JsonResponse
    {
        $errors = [];
        /** @var ConstraintViolationInterface $violation */
        foreach ($violations as $violation) {
            $errors[] = [
                'field' => $violation->getPropertyPath(),
                'message' => $violation->getMessage(),
            ];
        }

        return $this->json([
            'ok' => false,
            'errors' => $errors,
        ], Response::HTTP_UNPROCESSABLE_ENTITY);
    }
}
