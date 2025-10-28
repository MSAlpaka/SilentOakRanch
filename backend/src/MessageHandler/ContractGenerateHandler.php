<?php

namespace App\MessageHandler;

use App\Enum\BookingStatus;
use App\Enum\ContractStatus;
use App\Message\ContractQueued;
use App\Repository\BookingRepository;
use App\Repository\ContractRepository;
use App\Service\ContractGenerator;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
class ContractGenerateHandler
{
    public function __construct(
        private readonly BookingRepository $bookings,
        private readonly ContractRepository $contracts,
        private readonly ContractGenerator $generator,
        private readonly LoggerInterface $logger
    ) {
    }

    public function __invoke(ContractQueued $message): void
    {
        $booking = $this->bookings->find($message->getBookingId());
        if (!$booking) {
            $this->logger->warning('Contract generation skipped for missing booking', [
                'booking_id' => $message->getBookingId(),
                'trigger' => $message->getTrigger(),
            ]);

            return;
        }

        if ($booking->getStatus() !== BookingStatus::CONFIRMED) {
            $this->logger->info('Booking not eligible for contract generation', [
                'booking_id' => $booking->getId(),
                'status' => $booking->getStatus()->value,
            ]);

            return;
        }

        $contract = $this->contracts->findOneByBooking($booking);
        $result = $this->generator->generate($booking, $contract);

        $status = $result->getStatus() === ContractStatus::SIGNED ? 'signed' : 'generated';

        $this->logger->info('Contract generated successfully', [
            'booking_id' => $booking->getId(),
            'contract_uuid' => (string) $result->getId(),
            'status' => $status,
        ]);
    }
}
