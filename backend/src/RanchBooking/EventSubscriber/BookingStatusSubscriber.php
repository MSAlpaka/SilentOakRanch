<?php

namespace App\RanchBooking\EventSubscriber;

use App\RanchBooking\Event\BookingStatusChangedEvent;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;

class BookingStatusSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private readonly MailerInterface $mailer,
        private readonly LoggerInterface $logger,
        private readonly string $adminEmail = 'info@silent-oak-ranch.de'
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            BookingStatusChangedEvent::class => 'onBookingStatusChanged',
        ];
    }

    public function onBookingStatusChanged(BookingStatusChangedEvent $event): void
    {
        $booking = $event->getBooking();

        $email = (new Email())
            ->to($this->adminEmail)
            ->subject(sprintf('Booking %s status changed to %s', $booking->getId(), $event->getNewStatus()))
            ->text(sprintf(
                "Booking %s changed status from %s to %s at %s",
                $booking->getId(),
                $event->getPreviousStatus() ?? 'n/a',
                $event->getNewStatus(),
                $booking->getUpdatedAt()->format(DATE_ATOM)
            ));

        try {
            $this->mailer->send($email);
        } catch (\Throwable $exception) {
            $this->logger->warning('Failed to send booking status notification', [
                'booking' => (string) $booking->getId(),
                'error' => $exception->getMessage(),
            ]);
        }

        $this->logger->info('Booking status change dispatched to integrations', [
            'booking' => (string) $booking->getId(),
            'new_status' => $event->getNewStatus(),
        ]);
    }
}
