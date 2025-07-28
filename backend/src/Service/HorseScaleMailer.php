<?php

namespace App\Service;

use App\Entity\ScaleBooking;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;

class HorseScaleMailer
{
    public function __construct(private readonly MailerInterface $mailer)
    {
    }

    public function sendPendingBookingNotificationToAdmin(ScaleBooking $booking, string $adminEmail): void
    {
        $email = (new TemplatedEmail())
            ->to(new Address($adminEmail))
            ->subject('New HorseScale booking pending')
            ->htmlTemplate('horsescale/emails/pending_notification.html.twig')
            ->context(['booking' => $booking]);

        $this->mailer->send($email);
    }

    public function sendPaymentRequest(ScaleBooking $booking, string $paymentUrl): void
    {
        $email = (new TemplatedEmail())
            ->to(new Address($booking->getCustomerEmail(), $booking->getCustomerName()))
            ->subject('HorseScale payment required')
            ->htmlTemplate('horsescale/emails/payment_request.html.twig')
            ->context([
                'booking' => $booking,
                'paymentUrl' => $paymentUrl,
            ]);

        $this->mailer->send($email);
    }

    public function sendPaymentConfirmation(ScaleBooking $booking, string $pdfPath, string $qrPath): void
    {
        $email = (new TemplatedEmail())
            ->to(new Address($booking->getCustomerEmail(), $booking->getCustomerName()))
            ->subject('HorseScale booking confirmed')
            ->htmlTemplate('horsescale/emails/payment_confirmation.html.twig')
            ->context(['booking' => $booking])
            ->attachFromPath($pdfPath, 'booking.pdf')
            ->attachFromPath($qrPath, 'qrcode.png');

        $this->mailer->send($email);
    }

    public function sendResultEmail(ScaleBooking $booking): void
    {
        $email = (new TemplatedEmail())
            ->to(new Address($booking->getCustomerEmail(), $booking->getCustomerName()))
            ->subject('HorseScale result')
            ->htmlTemplate('horsescale/emails/result_notification.html.twig')
            ->context(['booking' => $booking]);

        $this->mailer->send($email);
    }
}
