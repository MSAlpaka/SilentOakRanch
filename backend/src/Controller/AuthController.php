<?php

namespace App\Controller;

use App\Entity\User;
use App\Enum\UserRole;
use App\Service\InvitationService;
use Doctrine\ORM\EntityManagerInterface;
use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Contracts\Translation\TranslatorInterface;
use DateTimeImmutable;

class AuthController extends AbstractController
{
    public function __construct(private TranslatorInterface $translator)
    {
    }
    #[Route('/api/register', name: 'api_register', methods: ['POST'])]
    public function register(
        Request $request,
        EntityManagerInterface $em,
        UserPasswordHasherInterface $passwordHasher,
        JWTTokenManagerInterface $jwtManager
    ): JsonResponse {
        $data = json_decode($request->getContent(), true);
        if (!is_array($data)) {
            return $this->json(['message' => $this->translator->trans('Invalid payload', [], 'validators')], 400);
        }

        $email = isset($data['email']) ? filter_var($data['email'], FILTER_VALIDATE_EMAIL) : null;
        $password = is_string($data['password'] ?? null) ? trim($data['password']) : null;
        $firstName = is_string($data['firstName'] ?? null) ? trim($data['firstName']) : '';
        $lastName = is_string($data['lastName'] ?? null) ? trim($data['lastName']) : '';

        if (!$email) {
            return $this->json(['message' => $this->translator->trans('Invalid email', [], 'validators')], 400);
        }

        if ($em->getRepository(User::class)->findOneBy(['email' => $email])) {
            return $this->json(['message' => $this->translator->trans('Email already exists', [], 'validators')], 400);
        }

        if (!$password) {
            return $this->json(['message' => $this->translator->trans('Password is required', [], 'validators')], 400);
        }

        if ($firstName === '') {
            return $this->json(['message' => $this->translator->trans('First name is required', [], 'validators')], 400);
        }

        if ($lastName === '') {
            return $this->json(['message' => $this->translator->trans('Last name is required', [], 'validators')], 400);
        }

        $role = UserRole::CUSTOMER;
        $roles = [sprintf('ROLE_%s', strtoupper($role->value))];
        $active = true;

        $user = new User();
        $user->setEmail($email);
        $user->setPassword($passwordHasher->hashPassword($user, $password));
        $user->setFirstName($firstName);
        $user->setLastName($lastName);
        $user->setRole($role);
        $user->setRoles($roles);
        $user->setActive($active);
        $user->setCreatedAt(new DateTimeImmutable());

        $em->persist($user);
        $em->flush();

        $token = $jwtManager->create($user);

        return $this->json([
            'token' => $token,
            'role' => $user->getRole()->value,
            'roles' => $user->getRoles(),
        ], 201);
    }

    #[Route('/api/login', name: 'api_login', methods: ['POST'])]
    public function login(
        Request $request,
        EntityManagerInterface $em,
        UserPasswordHasherInterface $passwordHasher,
        JWTTokenManagerInterface $jwtManager
    ): JsonResponse {
        $data = json_decode($request->getContent(), true);
        $email = $data['email'] ?? null;
        $password = $data['password'] ?? null;

        if (!$email || !$password) {
            return $this->json(['message' => $this->translator->trans('Email and password are required', [], 'validators')], 400);
        }

        $user = $em->getRepository(User::class)->findOneBy(['email' => $email]);
        if (!$user || !$passwordHasher->isPasswordValid($user, $password)) {
            return $this->json(['message' => $this->translator->trans('Invalid credentials', [], 'validators')], 401);
        }

        $token = $jwtManager->create($user);

        return $this->json([
            'token' => $token,
            'role' => $user->getRole()->value,
            'roles' => $user->getRoles(),
        ]);
    }

    #[Route('/api/invite', name: 'api_invite', methods: ['POST'])]
    public function invite(Request $request, InvitationService $invitationService): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        $email = $data['email'] ?? null;

        if (!$email) {
            return $this->json(['message' => $this->translator->trans('Email is required', [], 'validators')], 400);
        }

        $invitationService->sendInvitation($email);

        return $this->json(['message' => $this->translator->trans('Invitation sent', [], 'validators')]);
    }

    #[Route('/api/accept-invite/{token}', name: 'accept_invite', methods: ['POST'])]
    public function acceptInvite(
        string $token,
        Request $request,
        InvitationService $invitationService,
        EntityManagerInterface $em,
        UserPasswordHasherInterface $passwordHasher,
        JWTTokenManagerInterface $jwtManager
    ): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        if (!is_array($data)) {
            return $this->json(['message' => $this->translator->trans('Invalid payload', [], 'validators')], 400);
        }

        $password = is_string($data['password'] ?? null) ? trim($data['password']) : null;
        if (!$password) {
            return $this->json(['message' => $this->translator->trans('Password is required', [], 'validators')], 400);
        }

        $invitation = $invitationService->acceptInvitation($token);
        if (!$invitation) {
            return $this->json(['message' => $this->translator->trans('Invalid token', [], 'validators')], 400);
        }

        $user = $em->getRepository(User::class)->findOneBy(['email' => $invitation->getEmail()]);

        if (!$user) {
            return $this->json(['message' => $this->translator->trans('User not found', [], 'validators')], 404);
        }

        $user->setPassword($passwordHasher->hashPassword($user, $password));
        $user->setActive(true);

        $em->flush();

        $token = $jwtManager->create($user);

        return $this->json([
            'token' => $token,
            'role' => $user->getRole()->value,
            'roles' => $user->getRoles(),
        ]);
    }
}
