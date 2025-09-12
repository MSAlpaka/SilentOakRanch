<?php

namespace App\Tests;

use App\Entity\Appointment;
use App\Entity\User;
use App\Enum\UserRole;
use App\Security\AppointmentVoter;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use Symfony\Component\Security\Core\Authorization\Voter\VoterInterface;

class AppointmentVoterTest extends TestCase
{
    private function createUser(string $email, array $roles): User
    {
        return (new User())
            ->setEmail($email)
            ->setPassword('pw')
            ->setRoles($roles)
            ->setRole(UserRole::CUSTOMER)
            ->setFirstName('Test')
            ->setLastName('User')
            ->setActive(true)
            ->setCreatedAt(new \DateTimeImmutable());
    }

    public function testOwnerCanEditButOthersCannot(): void
    {
        $owner = $this->createUser('owner@example.com', []);
        $other = $this->createUser('other@example.com', []);
        $admin = $this->createUser('admin@example.com', ['ROLE_ADMIN']);

        $appointment = (new Appointment())->setOwner($owner);

        $voter = new AppointmentVoter();

        $ownerToken = new UsernamePasswordToken($owner, 'memory', $owner->getRoles());
        $this->assertSame(
            VoterInterface::ACCESS_GRANTED,
            $voter->vote($ownerToken, $appointment, [AppointmentVoter::EDIT])
        );

        $otherToken = new UsernamePasswordToken($other, 'memory', $other->getRoles());
        $this->assertSame(
            VoterInterface::ACCESS_DENIED,
            $voter->vote($otherToken, $appointment, [AppointmentVoter::EDIT])
        );

        $adminToken = new UsernamePasswordToken($admin, 'memory', $admin->getRoles());
        $this->assertSame(
            VoterInterface::ACCESS_GRANTED,
            $voter->vote($adminToken, $appointment, [AppointmentVoter::COMPLETE])
        );
    }
}

