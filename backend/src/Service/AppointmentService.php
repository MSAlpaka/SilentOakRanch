<?php

namespace App\Service;

use App\Entity\Appointment;
use App\Entity\Horse;
use App\Entity\ServiceProvider;
use App\Entity\ServiceType;
use App\Entity\User;
use App\Enum\AppointmentStatus;
use DateTimeInterface;
use Doctrine\ORM\EntityManagerInterface;

class AppointmentService
{
    public function __construct(private EntityManagerInterface $em)
    {
    }

    /**
     * Creates a new appointment and persists it.
     *
     * @param float[] $surcharges
     */
    public function createAppointment(
        Horse $horse,
        User $owner,
        ServiceType $serviceType,
        DateTimeInterface $start,
        ?ServiceProvider $provider = null,
        array $surcharges = [],
        ?string $notes = null
    ): Appointment {
        $end = (clone $start)->modify(sprintf('+%d minutes', $serviceType->getDefaultDurationMinutes()));

        $appointment = (new Appointment())
            ->setHorse($horse)
            ->setOwner($owner)
            ->setServiceType($serviceType)
            ->setStartTime($start)
            ->setEndTime($end)
            ->setServiceProvider($provider)
            ->setNotes($notes)
            ->setPrice($this->calculatePrice($serviceType, $surcharges))
            ->setStatus(AppointmentStatus::REQUESTED);

        $this->em->persist($appointment);
        $this->em->flush();

        return $appointment;
    }

    /**
     * Marks an appointment as confirmed.
     */
    public function confirm(Appointment $appointment): void
    {
        $appointment->setStatus(AppointmentStatus::CONFIRMED);
        $this->em->flush();
    }

    /**
     * Marks an appointment as completed.
     */
    public function complete(Appointment $appointment): void
    {
        $appointment->setStatus(AppointmentStatus::DONE);
        $this->em->flush();
    }

    /**
     * Cancels an appointment.
     */
    public function cancel(Appointment $appointment): void
    {
        $appointment->setStatus(AppointmentStatus::CANCELED);
        $this->em->flush();
    }

    /**
     * Calculates the final price based on base price and surcharges.
     *
     * @param float[] $surcharges
     */
    public function calculatePrice(ServiceType $serviceType, array $surcharges = []): string
    {
        $price = (float) $serviceType->getBasePrice();
        foreach ($surcharges as $surcharge) {
            $price += (float) $surcharge;
        }

        return number_format($price, 2, '.', '');
    }
}
