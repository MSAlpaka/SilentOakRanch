<?php

namespace App\Security;

use App\Entity\Appointment;
use App\Entity\User;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

class AppointmentVoter extends Voter
{
    public const VIEW = 'APPOINTMENT_VIEW';
    public const EDIT = 'APPOINTMENT_EDIT';
    public const COMPLETE = 'APPOINTMENT_COMPLETE';

    protected function supports(string $attribute, $subject): bool
    {
        return in_array($attribute, [self::VIEW, self::EDIT, self::COMPLETE], true)
            && $subject instanceof Appointment;
    }

    protected function voteOnAttribute(string $attribute, $subject, TokenInterface $token): bool
    {
        $user = $token->getUser();
        if (!$user instanceof User) {
            return false;
        }

        $isAdmin = in_array('ROLE_ADMIN', $user->getRoles(), true)
            || in_array('ROLE_STAFF', $user->getRoles(), true);

        /** @var Appointment $appointment */
        $appointment = $subject;

        return match ($attribute) {
            self::COMPLETE => $isAdmin,
            self::VIEW, self::EDIT => $isAdmin || $appointment->getOwner() === $user,
            default => false,
        };
    }
}

