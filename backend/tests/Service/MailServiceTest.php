<?php

namespace App\Tests;

use App\Service\MailService;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

class MailServiceTest extends TestCase
{
    #[DataProvider('appointmentEmailProvider')]
    public function testAppointmentEmailsUseLocalizedSubjects(
        string $method,
        string $translationKey,
        string $template
    ): void {
        $expectedSubject = sprintf('Localized %s', $translationKey);

        $mailer = $this->createMock(MailerInterface::class);
        $mailer->expects($this->once())
            ->method('send')
            ->with($this->callback(function (TemplatedEmail $email) use ($expectedSubject, $template) {
                $this->assertSame($expectedSubject, $email->getSubject());
                $this->assertSame($template, $email->getHtmlTemplate());

                return true;
            }));

        $translator = $this->createMock(TranslatorInterface::class);
        $translator->expects($this->once())
            ->method('trans')
            ->with($translationKey, [], 'emails')
            ->willReturn($expectedSubject);

        $service = new MailService($mailer, $translator);

        $service->{$method}(
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

    public static function appointmentEmailProvider(): iterable
    {
        yield 'confirmation' => [
            'sendAppointmentConfirmation',
            'appointment.confirmation.subject',
            'emails/appointments/confirmation.html.twig',
        ];

        yield 'reminder' => [
            'sendAppointmentReminder',
            'appointment.reminder.subject',
            'emails/appointments/reminder.html.twig',
        ];

        yield 'cancellation' => [
            'sendAppointmentCancellation',
            'appointment.cancellation.subject',
            'emails/appointments/cancellation.html.twig',
        ];
    }
}
