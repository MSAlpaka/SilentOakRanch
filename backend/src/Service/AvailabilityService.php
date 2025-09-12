<?php

namespace App\Service;

use App\Entity\ServiceProvider;
use App\Entity\ServiceType;
use App\Repository\AppointmentRepository;
use DateInterval;
use DateTimeImmutable;
use DateTimeInterface;

class AvailabilityService
{
    public function __construct(private AppointmentRepository $appointmentRepository)
    {
    }

    /**
     * Generates available time slots for a provider and service type on a given day.
     *
     * @return DateTimeImmutable[] list of start times
     */
    public function getAvailableSlots(ServiceProvider $provider, ServiceType $serviceType, DateTimeInterface $day): array
    {
        $duration = new DateInterval(sprintf('PT%dM', $serviceType->getDefaultDurationMinutes()));
        $dayStart = DateTimeImmutable::createFromInterface($day)->setTime(0, 0);
        $dayEnd = $dayStart->modify('+1 day');

        $slots = [];
        for ($slot = $dayStart; $slot < $dayEnd; $slot = $slot->add($duration)) {
            $slotEnd = $slot->add($duration);
            if (!$this->hasOverlap($provider, $slot, $slotEnd)) {
                $slots[] = $slot;
            }
        }

        return $slots;
    }

    /**
     * Checks whether the given time range overlaps existing appointments.
     */
    public function hasOverlap(ServiceProvider $provider, DateTimeInterface $start, DateTimeInterface $end): bool
    {
        return null !== $this->appointmentRepository->createQueryBuilder('a')
            ->andWhere('a.serviceProvider = :provider')
            ->andWhere('a.startTime < :end')
            ->andWhere('a.endTime > :start')
            ->setParameter('provider', $provider)
            ->setParameter('start', $start)
            ->setParameter('end', $end)
            ->getQuery()
            ->getOneOrNullResult();
    }
}
