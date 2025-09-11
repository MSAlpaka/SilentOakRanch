<?php

namespace App\Controller;

use App\Entity\Booking;
use App\Entity\Documentation;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

class DocumentationController extends AbstractController
{
    #[Route('/api/bookings/{id}/docs', methods: ['GET'])]
    #[IsGranted('ROLE_USER')]
    public function list(int $id, EntityManagerInterface $em, Security $security): JsonResponse
    {
        /** @var User|null $user */
        $user = $security->getUser();
        if (!$user instanceof User) {
            return $this->json(['message' => 'Unauthorized'], 401);
        }

        /** @var Booking|null $booking */
        $booking = $em->getRepository(Booking::class)->find($id);
        if (!$booking) {
            return $this->json(['message' => 'Booking not found'], 404);
        }

        $staff = $security->isGranted('ROLE_ADMIN') || $security->isGranted('ROLE_STAFF');
        if (!$staff && $booking->getUser() !== $user->getEmail()) {
            return $this->json(['message' => 'Forbidden'], 403);
        }

        $docs = $em->getRepository(Documentation::class)->findBy(['booking' => $booking], ['date' => 'ASC']);
        $data = array_map(fn(Documentation $doc) => $this->serializeDoc($doc), $docs);

        return $this->json($data);
    }

    #[Route('/api/bookings/{id}/docs', methods: ['POST'])]
    #[IsGranted('ROLE_USER')]
    public function create(int $id, Request $request, EntityManagerInterface $em, Security $security): JsonResponse
    {
        /** @var User|null $user */
        $user = $security->getUser();
        if (!$user instanceof User) {
            return $this->json(['message' => 'Unauthorized'], 401);
        }

        /** @var Booking|null $booking */
        $booking = $em->getRepository(Booking::class)->find($id);
        if (!$booking) {
            return $this->json(['message' => 'Booking not found'], 404);
        }

        $staff = $security->isGranted('ROLE_ADMIN') || $security->isGranted('ROLE_STAFF');
        if (!$staff && $booking->getUser() !== $user->getEmail()) {
            return $this->json(['message' => 'Forbidden'], 403);
        }

        $data = json_decode($request->getContent(), true);
        if (!is_array($data) || !isset($data['date'])) {
            return $this->json(['message' => 'Invalid payload'], 400);
        }

        $doc = new Documentation();
        $doc->setBooking($booking);
        $doc->setDate(new \DateTimeImmutable($data['date']));
        if (isset($data['notes'])) {
            $doc->setNotes($data['notes']);
        }
        if (isset($data['images']) && is_array($data['images'])) {
            $doc->setImages($data['images']);
        }

        $em->persist($doc);
        $em->flush();

        return $this->json($this->serializeDoc($doc), 201);
    }

    private function serializeDoc(Documentation $doc): array
    {
        return [
            'id' => $doc->getId(),
            'date' => $doc->getDate()->format('c'),
            'notes' => $doc->getNotes(),
            'images' => $doc->getImages(),
        ];
    }
}

