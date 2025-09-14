<?php

namespace App\Command;

use App\Entity\Invoice;
use App\Entity\InvoiceItem;
use App\Enum\InvoiceStatus;
use App\Enum\SubscriptionInterval;
use App\Enum\SubscriptionType;
use App\Repository\InvoiceRepository;
use App\Repository\SubscriptionRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: 'ranch:process-subscriptions',
    description: 'Processes due subscriptions and creates invoice items.'
)]
class ProcessSubscriptionsCommand extends Command
{
    public function __construct(
        private readonly SubscriptionRepository $subscriptionRepository,
        private readonly InvoiceRepository $invoiceRepository,
        private readonly EntityManagerInterface $em
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $now = new \DateTimeImmutable();
        $subscriptions = $this->subscriptionRepository->findDueSubscriptions($now);
        $processed = 0;

        foreach ($subscriptions as $subscription) {
            $user = $subscription->getUser();

            if ($subscription->getSubscriptionType() === SubscriptionType::STALL && $subscription->getStallUnit()) {
                $stall = $subscription->getStallUnit();
                if (!$subscription->getTitle()) {
                    $subscription->setTitle(sprintf('Boxenmiete %s', $stall->getName()));
                }
                $subscription->setAmount($stall->getMonthlyRent());
            }
            $period = $subscription->getNextDue()->format('Y-m');

            $invoice = $this->invoiceRepository->findOneBy([
                'user' => $user,
                'period' => $period,
            ]);

            if (!$invoice) {
                $invoice = new Invoice();
                $invoice->setUser($user);
                $invoice->setNumber(uniqid('INV-'));
                $invoice->setPeriod($period);
                $invoice->setCreatedAt(new \DateTimeImmutable());
                $invoice->setStatus(InvoiceStatus::SENT);
                $invoice->setCurrency('USD');
                $invoice->setTotal('0.00');
                $this->em->persist($invoice);
            }

            $itemRepo = $this->em->getRepository(InvoiceItem::class);
            $existing = $itemRepo->findOneBy([
                'invoice' => $invoice,
                'label' => $subscription->getTitle(),
            ]);

            if (!$existing) {
                $item = new InvoiceItem();
                $item->setInvoice($invoice);
                $item->setLabel($subscription->getTitle());
                $item->setAmount($subscription->getAmount());
                $this->em->persist($item);

                $newTotal = ((float) $invoice->getTotal()) + (float) $subscription->getAmount();
                $invoice->setTotal(number_format($newTotal, 2, '.', ''));
            }

            $next = $this->nextDueDate($subscription->getNextDue(), $subscription->getInterval());
            if (!$subscription->isAutoRenew() && $subscription->getEndDate() !== null && $next > $subscription->getEndDate()) {
                $subscription->setActive(false);
            } else {
                $subscription->setNextDue($next);
            }
            $this->em->persist($subscription);

            $processed++;
        }

        if ($processed > 0) {
            $this->em->flush();
        }

        $output->writeln(sprintf('%d subscriptions processed.', $processed));

        return Command::SUCCESS;
    }

    private function nextDueDate(\DateTimeImmutable $current, SubscriptionInterval $interval): \DateTimeImmutable
    {
        return match ($interval) {
            SubscriptionInterval::DAILY => $current->add(new \DateInterval('P1D')),
            SubscriptionInterval::WEEKLY => $current->add(new \DateInterval('P1W')),
            SubscriptionInterval::MONTHLY => $current->add(new \DateInterval('P1M')),
        };
    }
}
