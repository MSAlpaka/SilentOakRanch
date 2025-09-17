<?php

namespace App\Tests;

use App\Entity\Booking;
use App\Service\ReminderService;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use Psr\Log\AbstractLogger;
use Stringable;
use Symfony\Component\Mailer\MailerInterface;

class ReminderServiceTest extends TestCase
{
    public function testLogsDoNotExposeCredentials(): void
    {
        $mailer = $this->createStub(MailerInterface::class);
        $logger = new CollectingLogger();

        $service = new ReminderService($mailer, $logger, 'whatsapp://user:pass', 'sms://secret');
        $service->sendReminder($this->createBooking(), 12);

        $messages = array_map(static fn (array $record) => $record['message'], $logger->records);

        $this->assertContains('WhatsApp reminder for booking 42 (12 h) triggered for sending.', $messages);
        $this->assertContains('SMS reminder for booking 42 (12 h) triggered for sending.', $messages);

        $combinedMessages = implode(' ', $messages);
        $this->assertStringNotContainsString('whatsapp://user:pass', $combinedMessages);
        $this->assertStringNotContainsString('sms://secret', $combinedMessages);
    }

    public function testLogsIndicateMissingCredentials(): void
    {
        $mailer = $this->createStub(MailerInterface::class);
        $logger = new CollectingLogger();

        $service = new ReminderService($mailer, $logger);
        $service->sendReminder($this->createBooking(), 6);

        $messages = array_map(static fn (array $record) => $record['message'], $logger->records);

        $this->assertContains('WhatsApp reminder for booking 42 (6 h) not sent - no credentials configured.', $messages);
        $this->assertContains('SMS reminder for booking 42 (6 h) not sent - no credentials configured.', $messages);
    }

    private function createBooking(): Booking
    {
        $booking = new Booking();
        $booking->setUser('user@example.com');
        $booking->setLabel('Test Booking');
        $booking->setStartDate(new DateTimeImmutable('2024-01-01 12:00:00'));

        $idProperty = new \ReflectionProperty(Booking::class, 'id');
        $idProperty->setAccessible(true);
        $idProperty->setValue($booking, 42);

        return $booking;
    }
}

final class CollectingLogger extends AbstractLogger
{
    /** @var list<array{level: string, message: string, context: array}> */
    public array $records = [];

    /**
     * @param string $level
     * @param Stringable|string $message
     * @param array $context
     */
    public function log($level, Stringable|string $message, array $context = []): void
    {
        $this->records[] = [
            'level' => $level,
            'message' => (string) $message,
            'context' => $context,
        ];
    }
}
