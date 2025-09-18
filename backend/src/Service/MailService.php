<?php

namespace App\Service;

use App\Entity\Booking;
use App\Entity\Invoice;
use App\Entity\User;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;
use Symfony\Contracts\Translation\TranslatorInterface;

class MailService
{
    public function __construct(
        private readonly MailerInterface $mailer,
        private readonly TranslatorInterface $translator
    ) {
    }

    public function sendAppointmentConfirmation(
        string $email,
        string $userName,
        string $horseName,
        string $serviceType,
        string $date,
        string $time,
        string $providerName,
        string $stableName
    ): void {
        $message = (new TemplatedEmail())
            ->to(new Address($email))
            ->subject($this->translator->trans('appointment.confirmation.subject', [], 'emails'))
            ->htmlTemplate('emails/appointments/confirmation.html.twig')
            ->context([
                'userName' => $userName,
                'horseName' => $horseName,
                'serviceType' => $serviceType,
                'date' => $date,
                'time' => $time,
                'providerName' => $providerName,
                'stableName' => $stableName,
            ]);

        $this->mailer->send($message);
    }

    public function sendAppointmentReminder(
        string $email,
        string $userName,
        string $horseName,
        string $serviceType,
        string $date,
        string $time,
        string $providerName,
        string $stableName
    ): void {
        $message = (new TemplatedEmail())
            ->to(new Address($email))
            ->subject($this->translator->trans('appointment.reminder.subject', [], 'emails'))
            ->htmlTemplate('emails/appointments/reminder.html.twig')
            ->context([
                'userName' => $userName,
                'horseName' => $horseName,
                'serviceType' => $serviceType,
                'date' => $date,
                'time' => $time,
                'providerName' => $providerName,
                'stableName' => $stableName,
            ]);

        $this->mailer->send($message);
    }

    public function sendAppointmentCancellation(
        string $email,
        string $userName,
        string $horseName,
        string $serviceType,
        string $date,
        string $time,
        string $providerName,
        string $stableName
    ): void {
        $message = (new TemplatedEmail())
            ->to(new Address($email))
            ->subject($this->translator->trans('appointment.cancellation.subject', [], 'emails'))
            ->htmlTemplate('emails/appointments/cancellation.html.twig')
            ->context([
                'userName' => $userName,
                'horseName' => $horseName,
                'serviceType' => $serviceType,
                'date' => $date,
                'time' => $time,
                'providerName' => $providerName,
                'stableName' => $stableName,
            ]);

        $this->mailer->send($message);
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

    public function sendInvoice(User $user, Invoice $invoice, string $pdfPath): void
    {
        $email = (new TemplatedEmail())
            ->to(new Address($user->getEmail(), sprintf('%s %s', $user->getFirstName(), $user->getLastName())))
            ->subject('Invoice')
            ->html('<p>Please find your invoice attached.</p>')
            ->attachFromPath($pdfPath, 'invoice.pdf');

        $this->mailer->send($email);
    }
}
