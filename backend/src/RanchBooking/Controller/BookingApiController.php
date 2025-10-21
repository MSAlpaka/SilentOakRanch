<?php

namespace App\RanchBooking\Controller;

use App\RanchBooking\Entity\Booking;
use App\RanchBooking\Repository\BookingRepository;
use DateTimeImmutable;
use DateTimeInterface;
use DateTimeZone;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Uid\Uuid;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\ConstraintViolationListInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;

#[Route('/api/bookings', name: 'api_ranch_bookings_')]
class BookingApiController extends AbstractController
{
    public function __construct(
        private readonly BookingRepository $repository,
        private readonly ValidatorInterface $validator
    ) {
    }

    #[Route('', name: 'create', methods: ['POST'])]
    public function create(Request $request): JsonResponse
    {
        $payload = $this->decodeJson($request);
        if (!\is_array($payload)) {
            return $payload;
        }

        $violations = $this->validatePayload($payload);
        if ($violations->count() > 0) {
            return $this->validationErrorResponse($violations);
        }

        try {
            $data = $this->normalizeBookingData($payload);
        } catch (\InvalidArgumentException $exception) {
            return $this->problemResponse($exception->getMessage(), Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $existing = $this->repository->find($data['uuid']);

        if ($this->repository->hasOverlap($data['resource'], $data['slotStart'], $data['slotEnd'], $existing?->getId())) {
            return $this->problemResponse('Overlapping booking detected for resource.', Response::HTTP_CONFLICT);
        }

        $booking = $this->repository->createFromRequest($data, 'booking-api');

        return new JsonResponse([
            'ok' => true,
            'uuid' => (string) $booking->getId(),
        ], $existing instanceof Booking ? Response::HTTP_OK : Response::HTTP_CREATED);
    }

    #[Route('/{uuid}/status', name: 'status', requirements: ['uuid' => '[0-9a-fA-F\-]{36}'], methods: ['PATCH'])]
    public function updateStatus(string $uuid, Request $request): JsonResponse
    {
        if (!Uuid::isValid($uuid)) {
            return $this->problemResponse('Invalid UUID provided.', Response::HTTP_BAD_REQUEST);
        }

        $payload = $this->decodeJson($request);
        if (!\is_array($payload)) {
            return $payload;
        }

        $violations = $this->validateStatusPayload($payload);
        if ($violations->count() > 0) {
            return $this->validationErrorResponse($violations);
        }

        try {
            $booking = $this->repository->updateStatus($uuid, $payload['status'], 'booking-api');
        } catch (\InvalidArgumentException $exception) {
            $message = $exception->getMessage();
            $code = str_contains($message, 'not found') ? Response::HTTP_NOT_FOUND : Response::HTTP_UNPROCESSABLE_ENTITY;

            return $this->problemResponse($message, $code);
        }

        return new JsonResponse([
            'ok' => true,
            'uuid' => (string) $booking->getId(),
            'status' => $booking->getStatus(),
        ]);
    }

    #[Route('', name: 'recent', methods: ['GET'])]
    public function list(Request $request): JsonResponse
    {
        $sinceParam = $request->query->get('since');
        $since = null;

        if ($sinceParam !== null) {
            $since = DateTimeImmutable::createFromFormat(DateTimeInterface::ATOM, $sinceParam);
            if (!$since instanceof DateTimeImmutable) {
                return $this->problemResponse('Invalid since parameter format.', Response::HTTP_BAD_REQUEST);
            }
            $since = $since->setTimezone(new DateTimeZone('UTC'));
        }

        $bookings = $since instanceof DateTimeImmutable
            ? $this->repository->findRecent($since)
            : $this->repository->findAll();

        $data = array_map(static function (Booking $booking): array {
            return [
                'uuid' => (string) $booking->getId(),
                'resource' => $booking->getResource(),
                'name' => $booking->getName(),
                'phone' => $booking->getPhone(),
                'email' => $booking->getEmail(),
                'horse_name' => $booking->getHorseName(),
                'slot_start' => $booking->getSlotStart()->format(DateTimeInterface::ATOM),
                'slot_end' => $booking->getSlotEnd()->format(DateTimeInterface::ATOM),
                'price' => $booking->getPrice(),
                'status' => $booking->getStatus(),
                'source' => $booking->getSource(),
                'payment_ref' => $booking->getPaymentRef(),
                'synced_from' => $booking->getSyncedFrom(),
                'created_at' => $booking->getCreatedAt()->format(DateTimeInterface::ATOM),
                'updated_at' => $booking->getUpdatedAt()->format(DateTimeInterface::ATOM),
            ];
        }, $bookings);

        return new JsonResponse($data);
    }

    private function decodeJson(Request $request): array|JsonResponse
    {
        $payload = json_decode($request->getContent(), true);

        if (!\is_array($payload)) {
            return $this->problemResponse('Invalid JSON payload.', Response::HTTP_BAD_REQUEST);
        }

        return $payload;
    }

    private function validatePayload(array $payload): ConstraintViolationListInterface
    {
        $constraints = new Assert\Collection([
            'allowExtraFields' => true,
            'fields' => [
                'uuid' => [new Assert\NotBlank(), new Assert\Uuid()],
                'resource' => [new Assert\NotBlank(), new Assert\Choice(Booking::VALID_RESOURCES)],
                'slot_start' => [new Assert\NotBlank(), new Assert\DateTime(['format' => DateTimeInterface::ATOM])],
                'slot_end' => [new Assert\NotBlank(), new Assert\DateTime(['format' => DateTimeInterface::ATOM])],
                'price' => [new Assert\NotBlank(), new Assert\Regex('/^\d+(\.\d{1,2})?$/')],
                'status' => [new Assert\NotBlank(), new Assert\Choice(Booking::VALID_STATUSES)],
                'source' => [new Assert\Optional([new Assert\Choice(Booking::VALID_SOURCES)])],
                'name' => [new Assert\Optional([new Assert\Length(max: 255)])],
                'phone' => [new Assert\Optional([new Assert\Length(max: 64)])],
                'email' => [new Assert\Optional([new Assert\Email(), new Assert\Length(max: 255)])],
                'horse_name' => [new Assert\Optional([new Assert\Length(max: 255)])],
                'payment_ref' => [new Assert\Optional([new Assert\Length(max: 255)])],
                'synced_from' => [new Assert\Optional([new Assert\Length(max: 255)])],
            ],
        ]);

        return $this->validator->validate($payload, $constraints);
    }

    private function validateStatusPayload(array $payload): ConstraintViolationListInterface
    {
        $constraints = new Assert\Collection([
            'allowExtraFields' => false,
            'fields' => [
                'status' => [
                    new Assert\NotBlank(),
                    new Assert\Choice([
                        Booking::STATUS_PAID,
                        Booking::STATUS_CONFIRMED,
                        Booking::STATUS_COMPLETED,
                        Booking::STATUS_CANCELLED,
                    ]),
                ],
            ],
        ]);

        return $this->validator->validate($payload, $constraints);
    }

    /**
     * @param array<string, mixed> $payload
     *
     * @return array{uuid: Uuid, resource: string, slotStart: DateTimeImmutable, slotEnd: DateTimeImmutable, price: string, status: string, name?: ?string, phone?: ?string, email?: ?string, horseName?: ?string, source?: ?string, paymentRef?: ?string, syncedFrom?: ?string}
     */
    private function normalizeBookingData(array $payload): array
    {
        $uuid = Uuid::fromString($payload['uuid']);

        $slotStart = new DateTimeImmutable($payload['slot_start']);
        $slotEnd = new DateTimeImmutable($payload['slot_end']);

        if ($slotEnd <= $slotStart) {
            throw new \InvalidArgumentException('slot_end must be greater than slot_start');
        }

        $slotStart = $slotStart->setTimezone(new DateTimeZone('UTC'));
        $slotEnd = $slotEnd->setTimezone(new DateTimeZone('UTC'));

        $price = number_format((float) $payload['price'], 2, '.', '');
        if ((float) $price < 0) {
            throw new \InvalidArgumentException('price must be positive.');
        }

        return [
            'uuid' => $uuid,
            'resource' => $payload['resource'],
            'slotStart' => $slotStart,
            'slotEnd' => $slotEnd,
            'price' => $price,
            'status' => $payload['status'],
            'name' => $payload['name'] ?? null,
            'phone' => $payload['phone'] ?? null,
            'email' => $payload['email'] ?? null,
            'horseName' => $payload['horse_name'] ?? null,
            'source' => $payload['source'] ?? Booking::SOURCE_WEBSITE,
            'paymentRef' => $payload['payment_ref'] ?? null,
            'syncedFrom' => $payload['synced_from'] ?? null,
        ];
    }

    private function problemResponse(string $message, int $statusCode): JsonResponse
    {
        return new JsonResponse([
            'ok' => false,
            'error' => $message,
        ], $statusCode);
    }

    private function validationErrorResponse(ConstraintViolationListInterface $violations): JsonResponse
    {
        $errors = [];
        foreach ($violations as $violation) {
            $errors[] = [
                'field' => $violation->getPropertyPath(),
                'message' => $violation->getMessage(),
            ];
        }

        return new JsonResponse([
            'ok' => false,
            'errors' => $errors,
        ], Response::HTTP_UNPROCESSABLE_ENTITY);
    }
}
