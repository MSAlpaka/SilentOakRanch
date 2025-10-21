<?php

namespace App\RanchBooking\Repository;

use App\RanchBooking\Entity\Booking;
use App\RanchBooking\Entity\BookingHistory;
use App\RanchBooking\Event\BookingStatusChangedEvent;
use DateTimeImmutable;
use DateTimeInterface;
use DateTimeZone;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use InvalidArgumentException;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\Uid\Uuid;

/**
 * @extends ServiceEntityRepository<Booking>
 */
class BookingRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry, private readonly EventDispatcherInterface $dispatcher)
    {
        parent::__construct($registry, Booking::class);
    }

    /**
     * @return list<Booking>
     */
    public function findRecent(DateTimeInterface $since): array
    {
        return $this->createQueryBuilder('b')
            ->where('b.updatedAt >= :since')
            ->setParameter('since', $since)
            ->orderBy('b.updatedAt', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function updateStatus(string $uuid, string $status, ?string $changedBy = null): Booking
    {
        if (!Uuid::isValid($uuid)) {
            throw new InvalidArgumentException('Invalid booking UUID provided.');
        }

        if (!\in_array($status, Booking::VALID_STATUSES, true)) {
            throw new InvalidArgumentException('Invalid status value provided.');
        }

        $booking = $this->find(Uuid::fromString($uuid));

        if (!$booking instanceof Booking) {
            throw new InvalidArgumentException('Booking not found for status update.');
        }

        $previousStatus = $booking->getStatus();

        if ($previousStatus === $status) {
            return $booking;
        }

        $now = new DateTimeImmutable('now', new DateTimeZone('UTC'));

        $booking->setStatus($status);
        $booking->setUpdatedAt($now);

        $history = (new BookingHistory())
            ->setBooking($booking)
            ->setBookingUuid($booking->getId())
            ->setOldStatus($previousStatus)
            ->setNewStatus($status)
            ->setChangedAt($now)
            ->setChangedBy($changedBy);

        $this->_em->persist($history);
        $this->_em->flush();

        $this->dispatcher->dispatch(new BookingStatusChangedEvent($booking, $previousStatus, $status));

        return $booking;
    }

    /**
     * @param array{uuid: Uuid, resource: string, slotStart: DateTimeImmutable, slotEnd: DateTimeImmutable, price: string, status: string, name?: ?string, phone?: ?string, email?: ?string, horseName?: ?string, source?: ?string, paymentRef?: ?string, syncedFrom?: ?string} $data
     */
    public function createFromRequest(array $data, ?string $changedBy = null): Booking
    {
        $uuid = $data['uuid'];

        if (!\in_array($data['resource'], Booking::VALID_RESOURCES, true)) {
            throw new InvalidArgumentException('Invalid resource value provided.');
        }

        if (!\in_array($data['status'], Booking::VALID_STATUSES, true)) {
            throw new InvalidArgumentException('Invalid status value provided.');
        }

        if (isset($data['source']) && $data['source'] !== null && !\in_array($data['source'], Booking::VALID_SOURCES, true)) {
            throw new InvalidArgumentException('Invalid source value provided.');
        }

        $booking = $this->find($uuid);
        $now = new DateTimeImmutable('now', new DateTimeZone('UTC'));

        $isNew = false;

        if (!$booking instanceof Booking) {
            $booking = (new Booking())
                ->setId($uuid)
                ->setCreatedAt($now);
            $isNew = true;
        }

        $previousStatus = $booking->getStatus();

        $booking
            ->setResource($data['resource'])
            ->setSlotStart($data['slotStart'])
            ->setSlotEnd($data['slotEnd'])
            ->setPrice($data['price'])
            ->setStatus($data['status'])
            ->setSource($data['source'] ?? Booking::SOURCE_WEBSITE)
            ->setName($data['name'] ?? null)
            ->setPhone($data['phone'] ?? null)
            ->setEmail($data['email'] ?? null)
            ->setHorseName($data['horseName'] ?? null)
            ->setPaymentRef($data['paymentRef'] ?? null)
            ->setSyncedFrom($data['syncedFrom'] ?? null)
            ->setUpdatedAt($now);

        $this->_em->persist($booking);

        $statusChanged = $previousStatus !== $booking->getStatus();

        if ($statusChanged || $isNew) {
            $history = (new BookingHistory())
                ->setBooking($booking)
                ->setBookingUuid($booking->getId())
                ->setOldStatus($isNew ? null : $previousStatus)
                ->setNewStatus($booking->getStatus())
                ->setChangedAt($now)
                ->setChangedBy($changedBy);
            $this->_em->persist($history);
        }

        $this->_em->flush();

        if ($statusChanged) {
            $this->dispatcher->dispatch(new BookingStatusChangedEvent($booking, $previousStatus, $booking->getStatus()));
        }

        return $booking;
    }

    public function hasOverlap(string $resource, DateTimeInterface $slotStart, DateTimeInterface $slotEnd, ?Uuid $exclude = null): bool
    {
        $qb = $this->createQueryBuilder('b')
            ->where('b.resource = :resource')
            ->andWhere('b.slotStart < :end')
            ->andWhere('b.slotEnd > :start')
            ->setParameter('resource', $resource)
            ->setParameter('start', $slotStart)
            ->setParameter('end', $slotEnd)
            ->setMaxResults(1);

        if ($exclude instanceof Uuid) {
            $qb->andWhere('b.id != :exclude')->setParameter('exclude', $exclude);
        }

        return (bool) $qb->getQuery()->getOneOrNullResult();
    }
}
