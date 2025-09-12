<?php

namespace App\Controller;

use App\Entity\Booking;
use App\Entity\Documentation;
use App\Entity\User;
use App\Enum\DocumentationType;
use App\Service\DocumentationService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Contracts\Translation\TranslatorInterface;

class DocumentationController extends AbstractController
{
    public function __construct(private TranslatorInterface $translator)
    {
    }

    #[Route('/api/reko/{bookingId}/docs', methods: ['GET'])]
    #[IsGranted('ROLE_USER')]
    public function list(int $bookingId, EntityManagerInterface $em, Security $security): JsonResponse
    {
        /** @var User|null $user */
        $user = $security->getUser();
        if (!$user instanceof User) {
            return $this->json(['message' => $this->translator->trans('Unauthorized', [], 'validators')], 401);
        }

        /** @var Booking|null $booking */
        $booking = $em->getRepository(Booking::class)->find($bookingId);
        if (!$booking) {
            return $this->json(['message' => $this->translator->trans('Booking not found', [], 'validators')], 404);
        }

        $staff = $security->isGranted('ROLE_ADMIN') || $security->isGranted('ROLE_STAFF');
        if (!$staff && $booking->getUser() !== $user->getEmail()) {
            return $this->json(['message' => $this->translator->trans('Forbidden', [], 'validators')], 403);
        }

        $docs = $em->getRepository(Documentation::class)->findBy(['booking' => $booking], ['createdAt' => 'ASC']);
        $data = array_map(fn(Documentation $doc) => $this->serializeDoc($doc), $docs);

        return $this->json($data);
    }

    #[Route('/api/reko/{bookingId}/docs/new', methods: ['POST'])]
    #[IsGranted('ROLE_USER')]
    public function create(int $bookingId, Request $request, DocumentationService $documentationService, EntityManagerInterface $em, Security $security): JsonResponse
    {
        /** @var User|null $user */
        $user = $security->getUser();
        if (!$user instanceof User) {
            return $this->json(['message' => $this->translator->trans('Unauthorized', [], 'validators')], 401);
        }

        /** @var Booking|null $booking */
        $booking = $em->getRepository(Booking::class)->find($bookingId);
        if (!$booking) {
            return $this->json(['message' => $this->translator->trans('Booking not found', [], 'validators')], 404);
        }

        $staff = $security->isGranted('ROLE_ADMIN') || $security->isGranted('ROLE_STAFF');
        if (!$staff && $booking->getUser() !== $user->getEmail()) {
            return $this->json(['message' => $this->translator->trans('Forbidden', [], 'validators')], 403);
        }

        $data = json_decode($request->getContent(), true);
        if (!is_array($data) || !isset($data['type'])) {
            return $this->json(['message' => $this->translator->trans('Invalid payload', [], 'validators')], 400);
        }

        $type = DocumentationType::tryFrom($data['type']);
        if (!$type) {
            return $this->json(['message' => $this->translator->trans('Invalid documentation type', [], 'validators')], 400);
        }

        $payload = $data;
        unset($payload['type']);

        try {
            $doc = $documentationService->createDocumentation($booking, $type, $payload);
        } catch (\InvalidArgumentException $e) {
            return $this->json(['message' => $e->getMessage()], 400);
        }

        return $this->json($this->serializeDoc($doc), 201);
    }

    #[Route('/api/reko/docs/{id}/export', methods: ['GET'])]
    #[IsGranted('ROLE_USER')]
    public function export(int $id, DocumentationService $documentationService, EntityManagerInterface $em, Security $security): Response
    {
        /** @var User|null $user */
        $user = $security->getUser();
        if (!$user instanceof User) {
            return $this->json(['message' => $this->translator->trans('Unauthorized', [], 'validators')], 401);
        }

        /** @var Documentation|null $doc */
        $doc = $em->getRepository(Documentation::class)->find($id);
        if (!$doc) {
            return $this->json(['message' => $this->translator->trans('Documentation not found', [], 'validators')], 404);
        }

        $booking = $doc->getBooking();
        $staff = $security->isGranted('ROLE_ADMIN') || $security->isGranted('ROLE_STAFF');
        if (!$staff && $booking->getUser() !== $user->getEmail()) {
            return $this->json(['message' => $this->translator->trans('Forbidden', [], 'validators')], 403);
        }

        try {
            $content = $documentationService->export($doc);
        } catch (\RuntimeException $e) {
            return $this->json(['message' => $e->getMessage()], 400);
        }

        return new Response($content, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => sprintf('attachment; filename="documentation-%d.pdf"', $doc->getId()),
        ]);
    }

    private function serializeDoc(Documentation $doc): array
    {
        return [
            'id' => $doc->getId(),
            'type' => $doc->getType()->value,
            'notes' => $doc->getNotes(),
            'photos' => $doc->getPhotos(),
            'videos' => $doc->getVideos(),
            'metrics' => $doc->getMetrics(),
            'createdAt' => $doc->getCreatedAt()->format('c'),
        ];
    }
}


