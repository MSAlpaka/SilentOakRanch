<?php

namespace App\Service;

use App\Entity\Agreement;
use App\Entity\User;
use App\Enum\AgreementStatus;
use App\Enum\AgreementType;
use App\Repository\AgreementRepository;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\File\UploadedFile;

class AgreementService
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly AgreementRepository $agreementRepository,
        private readonly string $projectDir,
    ) {
    }

    public function giveConsent(User $user, AgreementType $type): Agreement
    {
        if ($this->agreementRepository->findActiveByUserAndType($user, $type)) {
            throw new \RuntimeException('Active agreement already exists for this user and type');
        }

        $agreement = (new Agreement())
            ->setUser($user)
            ->setType($type)
            ->setVersion('1.0')
            ->setConsentGiven(true)
            ->setConsentAt(new DateTimeImmutable())
            ->setStatus(AgreementStatus::ACTIVE);

        $this->em->persist($agreement);
        $this->em->flush();

        return $agreement;
    }

    public function uploadContract(User $user, UploadedFile $pdf, AgreementType $type, string $version): Agreement
    {
        if ($this->agreementRepository->findActiveByUserAndType($user, $type)) {
            throw new \RuntimeException('Active agreement already exists for this user and type');
        }

        $agreement = (new Agreement())
            ->setUser($user)
            ->setType($type)
            ->setVersion($version)
            ->setConsentGiven(true)
            ->setConsentAt(new DateTimeImmutable())
            ->setSignedAt(new DateTimeImmutable())
            ->setStatus(AgreementStatus::ACTIVE);

        $this->em->persist($agreement);
        $this->em->flush();

        $dir = sprintf('%s/public/agreements/%d', $this->projectDir, $user->getId());
        if (!is_dir($dir)) {
            mkdir($dir, 0777, true);
        }
        $filename = sprintf('%d.pdf', $agreement->getId());
        $pdf->move($dir, $filename);
        $relativePath = sprintf('agreements/%d/%s', $user->getId(), $filename);
        $agreement->setFilePath($relativePath);

        $this->applyDigitalSignature($agreement);

        $this->em->flush();

        return $agreement;
    }

    private function applyDigitalSignature(Agreement $agreement): void
    {
        // Placeholder for digital signature implementation
    }
}
