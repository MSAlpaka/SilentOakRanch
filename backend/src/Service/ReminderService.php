<?php

namespace App\Service;

use App\Entity\Booking;
use Psr\Log\LoggerInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;

class ReminderService
{
    public function __construct(
        private readonly MailerInterface $mailer,
        private readonly LoggerInterface $logger,
        private readonly ?string $whatsappDsn = null,
        private readonly ?string $smsDsn = null
    ) {
    }

    public function sendReminder(Booking $booking, int $hoursBefore): void
    {
        $this->sendEmail($booking, $hoursBefore);
        $this->sendWhatsApp($booking, $hoursBefore);
        $this->sendSms($booking, $hoursBefore);
    }

    private function sendEmail(Booking $booking, int $hoursBefore): void
    {
        $message = (new Email())
            ->to($booking->getUser())
            ->subject(sprintf('Reminder: %s', $booking->getLabel()))
            ->text(
                sprintf(
                    'Your booking starts at %s. This is a reminder sent %d hours before.',
                    $booking->getStartDate()->format('Y-m-d H:i'),
                    $hoursBefore
                )
            );

        $this->mailer->send($message);
    }

    private function sendWhatsApp(Booking $booking, int $hoursBefore): void
    {
        if (empty($this->whatsappDsn)) {
            $this->logger->info(
                sprintf(
                    'WhatsApp reminder for booking %d (%d h) not sent - no credentials configured.',
                    $booking->getId(),
                    $hoursBefore
                )
            );

            return;
        }

        $this->logger->info(
            sprintf(
                'WhatsApp reminder for booking %d (%d h) triggered for sending.',
                $booking->getId(),
                $hoursBefore
            )
        );
    }

    private function sendSms(Booking $booking, int $hoursBefore): void
    {
        if (empty($this->smsDsn)) {
            $this->logger->info(
                sprintf(
                    'SMS reminder for booking %d (%d h) not sent - no credentials configured.',
                    $booking->getId(),
                    $hoursBefore
                )
            );

            return;
        }

        $this->logger->info(
            sprintf(
                'SMS reminder for booking %d (%d h) triggered for sending.',
                $booking->getId(),
                $hoursBefore
            )
        );
    }
}
