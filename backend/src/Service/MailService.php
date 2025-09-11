<?php

namespace App\Service;

use App\Entity\Booking;
use App\Entity\Invoice;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;

class MailService
{
    public function __construct(private readonly MailerInterface $mailer)
    {
    }

    public function sendInvitation(string $email, string $token): void
    {
        $message = (new TemplatedEmail())
            ->to(new Address($email))
            ->subject('Invitation')
            ->htmlTemplate('emails/invitation.html.twig')
            ->context(['token' => $token]);

        $this->mailer->send($message);
    }

    public function sendBookingConfirmation(Booking $booking): void
    {
        $message = (new TemplatedEmail())
            ->to(new Address($booking->getUser()))
            ->subject('Booking Confirmation')
            ->htmlTemplate('emails/booking.html.twig')
            ->context(['booking' => $booking]);

        $this->mailer->send($message);
    }

    public function sendInvoiceDraft(Booking $booking): void
    {
        $message = (new TemplatedEmail())
            ->to(new Address($booking->getUser()))
            ->subject('Invoice Draft')
            ->htmlTemplate('emails/invoice_draft.html.twig')
            ->context(['booking' => $booking]);

        $this->mailer->send($message);
    }

    public function sendInvoice(Invoice $invoice): void
    {
        $user = $invoice->getUser();

        $email = (new TemplatedEmail())
            ->to(new Address($user->getEmail(), sprintf('%s %s', $user->getFirstName(), $user->getLastName())))
            ->subject('Invoice')
            ->html('<p>Please find your invoice attached.</p>')
            ->attachFromPath($invoice->getPdfPath(), 'invoice.pdf');

        $this->mailer->send($email);
    }
}
