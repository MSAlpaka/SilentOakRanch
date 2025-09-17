<?php

namespace App\Service;

use App\Entity\Horse;
use App\Entity\ScaleBooking;
use App\Entity\User;
use App\Enum\ScaleBookingStatus;
use App\Enum\ScaleBookingType;
use Doctrine\ORM\EntityManagerInterface;
use DateTimeInterface;

class ScaleBookingService
{
    public function __construct(
        private ScaleSlotService $slotService,
        private EntityManagerInterface $em,
        private QrCodeGenerator $qrCodeGenerator,
    ) {
    }

    /**
     * Validates that the given slot is available.
     */
    public function validateSlot(DateTimeInterface $slot): void
    {
        if (!$this->slotService->isSlotAvailable($slot)) {
            throw new \RuntimeException('Selected slot is not available');
        }
    }

    /**
     * Calculates the price for a booking based on its type.
     */
    public function calculatePrice(ScaleBookingType $type, ?DateTimeInterface $slot = null): string
    {
        $price = match ($type) {
            ScaleBookingType::SINGLE => 10.0,
            ScaleBookingType::PACKAGE => 45.0,
            ScaleBookingType::PREMIUM => 20.0,
            ScaleBookingType::DYNAMIC => $this->dynamicPrice($slot),
        };

        return number_format($price, 2, '.', '');
    }

    private function dynamicPrice(?DateTimeInterface $slot): float
    {
        $hour = (int) ($slot?->format('H') ?? '0');
        return 10 + $hour * 0.5;
    }

    /**
     * Creates and persists a new ScaleBooking and generates a QR token.
     */
    public function createBooking(Horse $horse, User $owner, DateTimeInterface $slot, ScaleBookingType $type): ScaleBooking
    {
        $this->validateSlot($slot);

        $booking = (new ScaleBooking())
            ->setId(bin2hex(random_bytes(16)))
            ->setHorse($horse)
            ->setOwner($owner)
            ->setSlot($slot)
            ->setBookingType($type)
            ->setPrice($this->calculatePrice($type, $slot))
            ->setStatus(ScaleBookingStatus::PENDING);

        $qrToken = bin2hex(random_bytes(16));
        $booking->setQrToken($qrToken);

        $this->em->persist($booking);
        $this->em->flush();

        $this->qrCodeGenerator->generate($qrToken);

        return $booking;
    }

    /**
     * Creates a normalized payload for API responses including a QR code image.
     */
    public function serializeBooking(ScaleBooking $booking): array
    {
        $qrBinary = $this->qrCodeGenerator->generate($booking->getQrToken());

        return [
            'id' => $booking->getId(),
            'horse' => [
                'name' => $booking->getHorse()->getName(),
            ],
            'slot' => $booking->getSlot()->format('c'),
            'status' => $booking->getStatus()->value,
            'price' => $booking->getPrice(),
            'weight' => $booking->getWeight(),
            'qrToken' => $booking->getQrToken(),
            'qrImage' => sprintf('data:image/png;base64,%s', base64_encode($qrBinary)),
        ];
    }
}
