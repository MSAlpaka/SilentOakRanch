<?php

namespace App\Controller;

use App\Entity\Agreement;
use App\Entity\User;
use App\Enum\AgreementType;
use App\Repository\AgreementRepository;
use App\Service\AgreementService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

class AgreementController extends AbstractController
{
    public function __construct(
        #[Autowire('%kernel.project_dir%')] private readonly string $projectDir
    ) {
    }

    #[Route('/api/agreements/consent', methods: ['POST'])]
    #[IsGranted('ROLE_USER')]
    public function giveConsentAction(Request $request, AgreementService $service, Security $security): JsonResponse
    {
        $user = $security->getUser();
        if (!$user instanceof User) {
            return $this->json(['message' => 'Unauthorized'], 401);
        }

        $data = json_decode($request->getContent(), true);
        if (!is_array($data) || !isset($data['type'])) {
            return $this->json(['message' => 'Invalid payload'], 400);
        }

        $type = AgreementType::tryFrom($data['type']);
        if (!$type) {
            return $this->json(['message' => 'Invalid agreement type'], 400);
        }

        try {
            $agreement = $service->giveConsent($user, $type);
        } catch (\RuntimeException $e) {
            return $this->json(['message' => $e->getMessage()], 400);
        }

        return $this->json([
            'id' => $agreement->getId(),
            'type' => $agreement->getType()->value,
            'version' => $agreement->getVersion(),
            'status' => $agreement->getStatus()->value,
        ], 201);
    }

    #[Route('/api/agreements/upload', methods: ['POST'])]
    #[IsGranted('ROLE_ADMIN')]
    public function uploadAction(
        Request $request,
        AgreementService $service,
        EntityManagerInterface $em,
    ): JsonResponse {
        $userId = $request->request->get('userId');
        $typeParam = $request->request->get('type');
        $version = $request->request->get('version');
        $file = $request->files->get('file');

        if (!$userId || !$typeParam || !$version || !$file) {
            return $this->json(['message' => 'Invalid payload'], 400);
        }

        /** @var User|null $user */
        $user = $em->getRepository(User::class)->find($userId);
        if (!$user) {
            return $this->json(['message' => 'User not found'], 404);
        }

        $type = AgreementType::tryFrom($typeParam);
        if (!$type) {
            return $this->json(['message' => 'Invalid agreement type'], 400);
        }

        $agreement = $service->uploadContract($user, $file, $type, $version);

        return $this->json([
            'id' => $agreement->getId(),
            'type' => $agreement->getType()->value,
            'version' => $agreement->getVersion(),
            'status' => $agreement->getStatus()->value,
        ], 201);
    }

    #[Route('/api/agreements', methods: ['GET'])]
    #[IsGranted('ROLE_USER')]
    public function listAction(AgreementRepository $repo, Security $security): JsonResponse
    {
        $user = $security->getUser();
        if (!$user instanceof User) {
            return $this->json(['message' => 'Unauthorized'], 401);
        }

        $agreements = $repo->findBy(['user' => $user]);

        $data = array_map(function (Agreement $a) {
            return [
                'id' => $a->getId(),
                'type' => $a->getType()->value,
                'version' => $a->getVersion(),
                'status' => $a->getStatus()->value,
                'filePath' => $a->getFilePath(),
            ];
        }, $agreements);

        return $this->json($data);
    }

    #[Route('/api/agreements/{id}', methods: ['GET'])]
    #[IsGranted('ROLE_USER')]
    public function downloadAction(int $id, AgreementRepository $repo, Security $security): Response
    {
        $agreement = $repo->find($id);
        if (!$agreement) {
            return $this->json(['message' => 'Not found'], 404);
        }

        $user = $security->getUser();
        if (!$user instanceof User) {
            return $this->json(['message' => 'Unauthorized'], 401);
        }

        if ($agreement->getUser() !== $user && !$security->isGranted('ROLE_ADMIN')) {
            return $this->json(['message' => 'Forbidden'], 403);
        }

        $filePath = $agreement->getFilePath();
        if (!$filePath) {
            return $this->json(['message' => 'File not found'], 404);
        }

        $relativePath = ltrim($filePath, '/');
        if (!str_starts_with($relativePath, 'agreements/')) {
            return $this->json(['message' => 'File not found'], 404);
        }

        $absolute = $this->projectDir . '/var/' . $relativePath;
        if (!is_file($absolute)) {
            return $this->json(['message' => 'File not found'], 404);
        }

        $response = new BinaryFileResponse($absolute);
        $response->headers->set('Content-Type', 'application/pdf');
        $response->setContentDisposition(
            ResponseHeaderBag::DISPOSITION_ATTACHMENT,
            basename($absolute)
        );

        return $response;
    }
}
