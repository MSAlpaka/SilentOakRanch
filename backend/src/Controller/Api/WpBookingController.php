<?php

namespace App\Controller\Api;

use App\Controller\Api\Dto\WpBookingRequest;
use App\Entity\Booking;
use App\Entity\StallUnit;
use App\Enum\BookingStatus;
use App\Enum\BookingType;
use App\Repository\BookingRepository;
use App\Repository\StallUnitRepository;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Validator\ConstraintViolationInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;

class WpBookingController extends AbstractController
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly ValidatorInterface $validator,
        private readonly StallUnitRepository $stallUnits,
        private readonly BookingRepository $bookings,
        private readonly LoggerInterface $logger
    ) {
    }

    #[Route('/api/wp/bookings', methods: ['POST'])]
    public function create(Request $request): JsonResponse
    {
        $payload = json_decode($request->getContent(), true);
        if (!\is_array($payload)) {
            return $this->json(['ok' => false, 'error' => 'Invalid JSON payload.'], Response::HTTP_BAD_REQUEST);
        }

        $dto = WpBookingRequest::fromPayload($payload);
        $violations = $this->validator->validate($dto);
        if ($violations->count() > 0) {
            return $this->validationErrorResponse($violations);
        }

        try {
            $start = $dto->getSlotStart();
            $end = $dto->getSlotEnd();
        } catch (\Throwable $exception) {
            return $this->json([
                'ok' => false,
                'error' => 'Invalid date range provided.',
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        if ($end <= $start) {
            return $this->json([
                'ok' => false,
                'error' => 'The provided end date must be after the start date.',
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $stallUnit = $this->resolveStallUnit($dto);
        if (!$stallUnit instanceof StallUnit) {
            return $this->json([
                'ok' => false,
                'error' => 'Referenced stall unit could not be resolved.',
            ], Response::HTTP_NOT_FOUND);
        }

        if ($this->bookings->hasOverlap($stallUnit, $start, $end)) {
            return $this->json([
                'ok' => false,
                'error' => 'The selected time range overlaps an existing booking.',
            ], Response::HTTP_CONFLICT);
        }

        $booking = new Booking();
        $booking
            ->setStallUnit($stallUnit)
            ->setStartDate($start)
            ->setEndDate($end)
            ->setDateFrom($start)
            ->setDateTo($end)
            ->setPrice($dto->getPrice())
            ->setType(BookingType::SERVICE)
            ->setStatus($dto->getBookingStatus())
            ->setIsConfirmed($dto->getBookingStatus() === BookingStatus::CONFIRMED)
            ->setLabel($this->buildLabel($dto))
            ->setUser($this->resolveUser($dto));

        $this->entityManager->persist($booking);
        $this->entityManager->flush();

        $this->logger->info('WordPress booking imported', [
            'booking_id' => $booking->getId(),
            'source_uuid' => $dto->getUuid(),
            'resource' => $dto->getResource(),
            'status' => $dto->getStatus(),
        ]);

        return $this->json([
            'ok' => true,
            'id' => $booking->getId(),
            'status' => $booking->getStatus()->value,
        ], Response::HTTP_CREATED);
    }

    private function buildLabel(WpBookingRequest $dto): string
    {
        $parts = [sprintf('WP %s', ucfirst($dto->getResource()))];
        if ($dto->getHorseName()) {
            $parts[] = $dto->getHorseName();
        } elseif ($dto->getName()) {
            $parts[] = $dto->getName();
        } else {
            $parts[] = $dto->getUuid();
        }

        return trim(implode(' - ', array_filter($parts)));
    }

    private function resolveUser(WpBookingRequest $dto): string
    {
        $email = $dto->getEmail();
        if ($email !== null && $email !== '') {
            return $email;
        }

        $fallback = $dto->getName() ?? $dto->getUuid();

        return $fallback !== '' ? $fallback : 'wordpress';
    }

    private function resolveStallUnit(WpBookingRequest $dto): ?StallUnit
    {
        if ($dto->getStallUnitId() !== null) {
            return $this->stallUnits->find($dto->getStallUnitId());
        }

        $candidates = [
            $dto->getResource(),
            ucfirst($dto->getResource()),
        ];

        foreach ($candidates as $name) {
            if ($name === '') {
                continue;
            }
            $stallUnit = $this->stallUnits->findOneBy(['name' => $name]);
            if ($stallUnit instanceof StallUnit) {
                return $stallUnit;
            }
        }

        return null;
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
