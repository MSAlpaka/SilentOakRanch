<?php

namespace App\Controller;

use App\Entity\User;
use App\Service\InvitationService;
use Doctrine\ORM\EntityManagerInterface;
use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Contracts\Translation\TranslatorInterface;

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
        $email = $data['email'] ?? null;
        $password = $data['password'] ?? null;

        if (!$email || !$password) {
            return $this->json(['message' => $this->translator->trans('Email and password are required', [], 'validators')], 400);
        }

        if ($em->getRepository(User::class)->findOneBy(['email' => $email])) {
            return $this->json(['message' => $this->translator->trans('Email already exists', [], 'validators')], 400);
        }

        $user = new User();
        $user->setEmail($email);
        $user->setPassword($passwordHasher->hashPassword($user, $password));

        $em->persist($user);
        $em->flush();

        $token = $jwtManager->create($user);

        return $this->json(['token' => $token], 201);
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

        return $this->json(['token' => $token]);
    }

    #[Route('/invite', name: 'invite', methods: ['POST'])]
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

    #[Route('/accept-invite/{token}', name: 'accept_invite', methods: ['POST'])]
    public function acceptInvite(string $token, InvitationService $invitationService): JsonResponse
    {
        if (!$invitationService->acceptInvitation($token)) {
            return $this->json(['message' => $this->translator->trans('Invalid token', [], 'validators')], 400);
        }

        return $this->json(['message' => $this->translator->trans('Invitation accepted', [], 'validators')]);
    }
}
