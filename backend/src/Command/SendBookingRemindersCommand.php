<?php

namespace App\Command;

use App\Repository\BookingRepository;
use App\Service\ReminderService;
use DateTimeImmutable;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: 'ranch:send-booking-reminders',
    description: 'Sends T-24h and T-2h reminders for upcoming bookings.'
)]
class SendBookingRemindersCommand extends Command
{
    public function __construct(
        private readonly BookingRepository $bookingRepository,
        private readonly ReminderService $reminderService
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $now = new DateTimeImmutable();
        $soon = $now->modify('+25 hours');

        $bookings = $this->bookingRepository->createQueryBuilder('b')
            ->where('b.startDate > :now')
            ->andWhere('b.startDate < :soon')
            ->setParameter('now', $now)
            ->setParameter('soon', $soon)
            ->getQuery()
            ->getResult();

        foreach ($bookings as $booking) {
            $diffHours = ($booking->getStartDate()->getTimestamp() - $now->getTimestamp()) / 3600;

            if ($diffHours >= 23.5 && $diffHours <= 24.5) {
                $this->reminderService->sendReminder($booking, 24);
                $output->writeln(sprintf('24h reminder sent for booking %d', $booking->getId()));
            } elseif ($diffHours >= 1.5 && $diffHours <= 2.5) {
                $this->reminderService->sendReminder($booking, 2);
                $output->writeln(sprintf('2h reminder sent for booking %d', $booking->getId()));
            }
        }

        return Command::SUCCESS;
    }
}
