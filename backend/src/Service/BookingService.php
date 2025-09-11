<?php

namespace App\Service;

use App\Entity\AddOn;
use App\Entity\Booking;
use App\Entity\Package;
use Doctrine\ORM\EntityManagerInterface;

class BookingService
{
    public function __construct(
        private EntityManagerInterface $em,
        private CalendarService $calendarService
    ) {
    }

    /**
     * @param AddOn[] $addOns
     */
    public function createBooking(Package $package, \DateTimeInterface $start, array $addOns = []): Booking
    {
        $end = (clone $start)->modify(sprintf('+%d day', $package->getDuration()));

        if (!$this->calendarService->isRangeFree($start, $end)) {
            throw new \RuntimeException('Date range is not available');
        }

        $booking = new Booking();
        $booking->setPackage($package)
            ->setStartDate($start)
            ->setEndDate($end)
            ->setDateFrom($start)
            ->setDateTo($end);

        $price = (float) $package->getDailyRate() * $package->getDuration();

        foreach ($addOns as $addOn) {
            $booking->addAddOn($addOn);
            $price += (float) $addOn->getPrice();
        }

        $booking->setPrice(number_format($price, 2, '.', ''));

        $this->em->persist($booking);
        $this->em->flush();

        return $booking;
    }
}
