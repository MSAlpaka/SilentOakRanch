<?php

namespace App\Service;

use App\Entity\Invitation;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;

class InvitationService
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly MailerInterface $mailer
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

        $message = (new Email())
            ->to($email)
            ->subject('Invitation')
            ->text('Use this token to accept your invitation: ' . $token);

        $this->mailer->send($message);

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
