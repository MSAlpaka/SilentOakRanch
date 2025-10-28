<?php

namespace App\Service;

use App\Entity\Booking;
use App\Entity\Contract;
use App\Enum\ContractStatus;
use App\Repository\ContractRepository;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Filesystem\Filesystem;

class ContractGenerator
{
    public function __construct(
        private readonly \Twig\Environment $twig,
        private readonly PdfGenerator $pdfGenerator,
        private readonly ContractRepository $contracts,
        private readonly EntityManagerInterface $entityManager,
        private readonly Filesystem $filesystem,
        private readonly string $contractsStoragePath
    ) {
    }

    public function generate(Booking $booking, ?Contract $contract = null): Contract
    {
        $this->ensureStorageDirectory();

        $contract ??= new Contract();
        $contract->setBooking($booking);

        $context = [
            'booking' => $booking,
            'contract' => $contract,
            'generatedAt' => new DateTimeImmutable('now'),
        ];

        $html = $this->twig->render('contracts/default.html.twig', $context);
        $pdfContent = $this->pdfGenerator->generatePdf($html);
        $hash = hash('sha256', $pdfContent);
        $filename = sprintf('%s/%s.pdf', rtrim($this->contractsStoragePath, '/'), $contract->getId()->toRfc4122());

        $this->filesystem->dumpFile($filename, $pdfContent);

        $contract
            ->setPath($filename)
            ->setHash($hash)
            ->setStatus(ContractStatus::GENERATED);
        $contract->appendAuditEntry('generated', $hash);

        if (!$this->contracts->find($contract->getId())) {
            $this->entityManager->persist($contract);
        }

        $this->entityManager->flush();

        return $contract;
    }

    private function ensureStorageDirectory(): void
    {
        if ($this->filesystem->exists($this->contractsStoragePath)) {
            return;
        }

        $this->filesystem->mkdir($this->contractsStoragePath, 0700);
    }
}
