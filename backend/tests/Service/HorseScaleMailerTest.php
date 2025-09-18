<?php

namespace App\Tests;

use App\Entity\ScaleBooking;
use App\Entity\User;
use App\Service\HorseScaleMailer;
use PHPUnit\Framework\TestCase;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\Mailer\MailerInterface;

class HorseScaleMailerTest extends TestCase
{
    public function testSendPendingBookingNotificationToAdminUsesProvidedAddress(): void
    {
        $booking = $this->createBookingWithOwner('owner@example.com');
        $adminEmail = 'admin@example.com';

        $mailer = $this->createMock(MailerInterface::class);
        $mailer->expects($this->once())
            ->method('send')
            ->with($this->callback(function (TemplatedEmail $email) use ($adminEmail) {
                $recipients = $email->getTo();
                $this->assertCount(1, $recipients);
                $this->assertSame($adminEmail, $recipients[0]->getAddress());

                return true;
            }));

        $mailerService = new HorseScaleMailer($mailer);
        $mailerService->sendPendingBookingNotificationToAdmin($booking, $adminEmail);
    }

    public function testSendPaymentRequestUsesOwnerAddress(): void
    {
        $booking = $this->createBookingWithOwner('owner@example.com', 'Horse', 'Owner');

        $mailer = $this->createMock(MailerInterface::class);
        $mailer->expects($this->once())
            ->method('send')
            ->with($this->callback(function (TemplatedEmail $email) {
                $recipients = $email->getTo();
                $this->assertCount(1, $recipients);
                $this->assertSame('owner@example.com', $recipients[0]->getAddress());
                $this->assertSame('Horse Owner', $recipients[0]->getName());

                return true;
            }));

        $mailerService = new HorseScaleMailer($mailer);
        $mailerService->sendPaymentRequest($booking, 'https://payments.test/example');
    }

    public function testSendPaymentConfirmationUsesOwnerAddress(): void
    {
        $booking = $this->createBookingWithOwner('owner@example.com', 'Horse', 'Owner');

        $mailer = $this->createMock(MailerInterface::class);
        $mailer->expects($this->once())
            ->method('send')
            ->with($this->callback(function (TemplatedEmail $email) {
                $recipients = $email->getTo();
                $this->assertCount(1, $recipients);
                $this->assertSame('owner@example.com', $recipients[0]->getAddress());
                $this->assertSame('Horse Owner', $recipients[0]->getName());

                return true;
            }));

        $mailerService = new HorseScaleMailer($mailer);
        $mailerService->sendPaymentConfirmation($booking, '/tmp/confirmation.pdf', '/tmp/qrcode.png');
    }

    public function testSendResultEmailFallsBackToEmailWhenNameMissing(): void
    {
        $booking = $this->createBookingWithOwner('owner@example.com', '   ', '');

        $mailer = $this->createMock(MailerInterface::class);
        $mailer->expects($this->once())
            ->method('send')
            ->with($this->callback(function (TemplatedEmail $email) {
                $recipients = $email->getTo();
                $this->assertCount(1, $recipients);
                $this->assertSame('owner@example.com', $recipients[0]->getAddress());
                $this->assertSame('owner@example.com', $recipients[0]->getName());

                return true;
            }));

        $mailerService = new HorseScaleMailer($mailer);
        $mailerService->sendResultEmail($booking);
    }

    private function createBookingWithOwner(string $email, string $firstName = 'First', string $lastName = 'Last'): ScaleBooking
    {
        $owner = new User();
        $owner->setEmail($email);
        $owner->setPassword('password');
        $owner->setFirstName($firstName);
        $owner->setLastName($lastName);

        $booking = new ScaleBooking();
        $booking->setOwner($owner);

        return $booking;
    }
}
