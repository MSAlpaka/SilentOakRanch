<?php

namespace App\Tests;

use App\Service\MailService;
use PHPUnit\Framework\TestCase;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

class MailServiceTest extends TestCase
{
    public function testSendAppointmentConfirmation(): void
    {
        $mailer = $this->createMock(MailerInterface::class);
        $mailer->expects($this->once())
            ->method('send')
            ->with($this->callback(function (TemplatedEmail $email) {
                $this->assertSame('Confirmation', $email->getSubject());
                $this->assertSame('emails/appointments/confirmation.html.twig', $email->getHtmlTemplate());
                return true;
            }));

        $translator = $this->createMock(TranslatorInterface::class);
        $translator->expects($this->once())
            ->method('trans')
            ->with('appointment.confirmation.subject')
            ->willReturn('Confirmation');

        $service = new MailService($mailer, $translator);
        $service->sendAppointmentConfirmation(
            'user@example.com',
            'User',
            'Horse',
            'Service',
            '2024-01-01',
            '10:00',
            'Provider',
            'Stable'
        );
    }

    public function testSendAppointmentReminder(): void
    {
        $mailer = $this->createMock(MailerInterface::class);
        $mailer->expects($this->once())
            ->method('send')
            ->with($this->callback(function (TemplatedEmail $email) {
                $this->assertSame('Reminder', $email->getSubject());
                $this->assertSame('emails/appointments/reminder.html.twig', $email->getHtmlTemplate());
                return true;
            }));

        $translator = $this->createMock(TranslatorInterface::class);
        $translator->expects($this->once())
            ->method('trans')
            ->with('appointment.reminder.subject')
            ->willReturn('Reminder');

        $service = new MailService($mailer, $translator);
        $service->sendAppointmentReminder(
            'user@example.com',
            'User',
            'Horse',
            'Service',
            '2024-01-01',
            '10:00',
            'Provider',
            'Stable'
        );
    }

    public function testSendAppointmentCancellation(): void
    {
        $mailer = $this->createMock(MailerInterface::class);
        $mailer->expects($this->once())
            ->method('send')
            ->with($this->callback(function (TemplatedEmail $email) {
                $this->assertSame('Cancellation', $email->getSubject());
                $this->assertSame('emails/appointments/cancellation.html.twig', $email->getHtmlTemplate());
                return true;
            }));

        $translator = $this->createMock(TranslatorInterface::class);
        $translator->expects($this->once())
            ->method('trans')
            ->with('appointment.cancellation.subject')
            ->willReturn('Cancellation');

        $service = new MailService($mailer, $translator);
        $service->sendAppointmentCancellation(
            'user@example.com',
            'User',
            'Horse',
            'Service',
            '2024-01-01',
            '10:00',
            'Provider',
            'Stable'
        );
    }
}
