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
    private ?string $signatureCertificatePath;
    private ?string $signaturePrivateKeyPath;
    private ?string $signaturePrivateKeyPassphrase;

    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly AgreementRepository $agreementRepository,
        private readonly string $projectDir,
        ?string $signatureCertificatePath = null,
        ?string $signaturePrivateKeyPath = null,
        ?string $signaturePrivateKeyPassphrase = null,
    ) {
        $this->signatureCertificatePath = $this->nullIfEmpty($signatureCertificatePath);
        $this->signaturePrivateKeyPath = $this->nullIfEmpty($signaturePrivateKeyPath);
        $this->signaturePrivateKeyPassphrase = $this->nullIfEmpty($signaturePrivateKeyPassphrase);
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

        $dir = sprintf('%s/var/agreements/%d', $this->projectDir, $user->getId());
        if (!is_dir($dir) && !mkdir($dir, 0777, true) && !is_dir($dir)) {
            throw new \RuntimeException(sprintf('Unable to create agreements directory "%s"', $dir));
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
        $relativePath = $agreement->getFilePath();
        if ($relativePath === null) {
            throw new \RuntimeException('Cannot sign an agreement without an associated PDF file.');
        }

        if ($this->signatureCertificatePath === null || $this->signaturePrivateKeyPath === null) {
            throw new \RuntimeException('Digital signature configuration is missing.');
        }

        $pdfPath = sprintf('%s/var/%s', $this->projectDir, ltrim($relativePath, '/'));
        if (!is_file($pdfPath)) {
            throw new \RuntimeException(sprintf('Agreement PDF not found at "%s"', $pdfPath));
        }

        $certificate = @file_get_contents($this->signatureCertificatePath);
        if ($certificate === false) {
            throw new \RuntimeException(sprintf('Unable to read signing certificate from "%s"', $this->signatureCertificatePath));
        }

        $privateKeyContent = @file_get_contents($this->signaturePrivateKeyPath);
        if ($privateKeyContent === false) {
            throw new \RuntimeException(sprintf('Unable to read signing key from "%s"', $this->signaturePrivateKeyPath));
        }

        $privateKey = openssl_pkey_get_private($privateKeyContent, $this->signaturePrivateKeyPassphrase ?? '');
        if ($privateKey === false) {
            throw new \RuntimeException('Unable to load signing key: ' . $this->collectOpenSslErrors());
        }

        $pdfBinary = @file_get_contents($pdfPath);
        if ($pdfBinary === false) {
            $privateKey = null;
            throw new \RuntimeException(sprintf('Unable to read agreement PDF at "%s"', $pdfPath));
        }

        $signature = '';

        try {
            if (!openssl_sign($pdfBinary, $signature, $privateKey, OPENSSL_ALGO_SHA256)) {
                throw new \RuntimeException('Failed to sign agreement PDF: ' . $this->collectOpenSslErrors());
            }
        } finally {
            $privateKey = null;
        }

        $signaturePayload = [
            'algorithm' => 'sha256WithRSAEncryption',
            'signature' => base64_encode($signature),
            'certificate' => base64_encode($certificate),
            'created_at' => (new DateTimeImmutable())->format(DATE_ATOM),
        ];

        $signatureJson = json_encode($signaturePayload, JSON_PRETTY_PRINT);
        if ($signatureJson === false) {
            throw new \RuntimeException('Failed to encode signature metadata: ' . json_last_error_msg());
        }

        $signaturePath = $pdfPath . '.sig';
        if (@file_put_contents($signaturePath, $signatureJson) === false) {
            throw new \RuntimeException(sprintf('Unable to write signature file "%s"', $signaturePath));
        }
    }

    private function collectOpenSslErrors(): string
    {
        $errors = [];
        while (($message = openssl_error_string()) !== false) {
            $errors[] = $message;
        }

        return $errors === [] ? 'unknown error' : implode('; ', $errors);
    }

    private function nullIfEmpty(?string $value): ?string
    {
        return $value === null || $value === '' ? null : $value;
    }
}
