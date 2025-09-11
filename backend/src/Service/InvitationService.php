<?php

namespace App\Service;

use App\Entity\Invitation;
use Doctrine\ORM\EntityManagerInterface;

class InvitationService
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly MailService $mailService
    ) {
    }

    public function sendInvitation(string $email): string
    {
        $token = bin2hex(random_bytes(32));

        $invitation = new Invitation();
        $invitation->setEmail($email);
        $invitation->setToken($token);

        $this->entityManager->persist($invitation);
        $this->entityManager->flush();

        $this->mailService->sendInvitation($email, $token);

        return $token;
    }

    public function acceptInvitation(string $token): bool
    {
        $invitation = $this->entityManager->getRepository(Invitation::class)
            ->findOneBy(['token' => $token]);

        if (!$invitation || $invitation->isAccepted()) {
            return false;
        }

        $invitation->setAccepted(true);
        $this->entityManager->flush();

        return true;
    }
}
