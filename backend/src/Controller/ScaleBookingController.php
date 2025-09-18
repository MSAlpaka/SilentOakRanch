<?php

namespace App\Controller;

use App\Entity\Horse;
use App\Entity\ScaleBooking;
use App\Entity\User;
use App\Enum\ScaleBookingStatus;
use App\Enum\ScaleBookingType;
use App\Repository\ScaleBookingRepository;
use App\Service\ScaleBookingService;
use App\Service\ScaleSlotService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

class ScaleBookingController extends AbstractController
{
    #[Route('/api/scale/slots', name: 'api_scale_slots', methods: ['GET'])]
    public function slots(Request $request, ScaleSlotService $slotService): JsonResponse
    {
        $dayParam = $request->query->get('day');
        try {
            $day = $dayParam ? new \DateTimeImmutable($dayParam) : new \DateTimeImmutable();
        } catch (\Exception) {
            return $this->json(['message' => 'Invalid date'], 400);
        }

        $slots = array_map(fn(\DateTimeImmutable $slot) => $slot->format('c'), $slotService->getAvailableSlots($day));

        return $this->json($slots);
    }

    #[Route('/api/scale/bookings', name: 'api_scale_bookings', methods: ['GET'])]
    #[IsGranted('ROLE_ADMIN')]
    public function bookings(ScaleBookingRepository $repository, ScaleBookingService $bookingService): JsonResponse
    {
        $bookings = array_map(
            fn(ScaleBooking $booking) => $bookingService->serializeBooking($booking),
            $repository->findAll()
        );

        return $this->json($bookings);
    }

    #[Route('/api/scale/bookings/my', name: 'api_scale_my_bookings', methods: ['GET'])]
    #[IsGranted('ROLE_USER')]
    public function myBookings(
        ScaleBookingRepository $repository,
        ScaleBookingService $bookingService,
        Security $security
    ): JsonResponse {
        $user = $security->getUser();
        if (!$user instanceof User) {
            return $this->json(['message' => 'Unauthorized'], 401);
        }

        $bookings = array_map(
            fn(ScaleBooking $booking) => $bookingService->serializeBooking($booking),
            $repository->findBy(['owner' => $user], ['slot' => 'ASC'])
        );

        return $this->json($bookings);
    }

    #[Route('/api/scale/bookings', name: 'api_scale_create_booking', methods: ['POST'])]
    #[IsGranted('ROLE_USER')]
    public function createBooking(
        Request $request,
        EntityManagerInterface $em,
        ScaleBookingService $bookingService,
        Security $security
    ): JsonResponse {
        $data = json_decode($request->getContent(), true);
        if (!isset($data['horseId'], $data['slot'], $data['type'])) {
            return $this->json(['message' => 'Invalid payload'], 400);
        }

        $horse = $em->getRepository(Horse::class)->find($data['horseId']);
        if (!$horse) {
            return $this->json(['message' => 'Horse not found'], 404);
        }

        $user = $security->getUser();
        if (!$user instanceof User) {
            return $this->json(['message' => 'Unauthorized'], 401);
        }

        if (!$this->isPrivileged($security) && !$this->isHorseOwnedByUser($horse, $user)) {
            return $this->json(['message' => 'Forbidden'], 403);
        }

        try {
            $slot = new \DateTimeImmutable($data['slot']);
        } catch (\Exception) {
            return $this->json(['message' => 'Invalid slot'], 400);
        }

        try {
            $type = ScaleBookingType::from($data['type']);
        } catch (\ValueError) {
            return $this->json(['message' => 'Invalid type'], 400);
        }

        try {
            $booking = $bookingService->createBooking($horse, $user, $slot, $type);
        } catch (\RuntimeException $e) {
            return $this->json(['message' => $e->getMessage()], 400);
        }

        return $this->json([
            'id' => $booking->getId(),
            'status' => $booking->getStatus()->value,
            'price' => $booking->getPrice(),
        ], 201);
    }

    #[Route('/api/scale/bookings/{id}/confirm', name: 'api_scale_confirm_booking', methods: ['POST'])]
    #[IsGranted('ROLE_ADMIN')]
    public function confirmBooking(
        string $id,
        ScaleBookingRepository $repository,
        EntityManagerInterface $em
    ): JsonResponse {
        $booking = $repository->find($id);
        if (!$booking) {
            return $this->json(['message' => 'Not found'], 404);
        }

        $booking->setStatus(ScaleBookingStatus::CONFIRMED);
        $em->flush();

        return $this->json(['status' => $booking->getStatus()->value]);
    }

    #[Route('/api/scale/bookings/{id}/weight', name: 'api_scale_record_weight', methods: ['POST'])]
    #[IsGranted('ROLE_ADMIN')]
    public function recordWeight(
        string $id,
        Request $request,
        ScaleBookingRepository $repository,
        EntityManagerInterface $em
    ): JsonResponse {
        $booking = $repository->find($id);
        if (!$booking) {
            return $this->json(['message' => 'Not found'], 404);
        }

        $data = json_decode($request->getContent(), true);
        if (!isset($data['weight'])) {
            return $this->json(['message' => 'Invalid payload'], 400);
        }

        $booking->setWeight((float) $data['weight'])
            ->setStatus(ScaleBookingStatus::COMPLETED);
        $em->flush();

        return $this->json([
            'status' => $booking->getStatus()->value,
            'weight' => $booking->getWeight(),
        ]);
    }

    private function isPrivileged(Security $security): bool
    {
        return $security->isGranted('ROLE_ADMIN') || $security->isGranted('ROLE_STAFF');
    }

    private function isHorseOwnedByUser(Horse $horse, User $user): bool
    {
        $owner = $horse->getOwner();

        if ($owner === $user) {
            return true;
        }

        $ownerId = $owner->getId();
        $userId = $user->getId();

        if (null !== $ownerId && null !== $userId) {
            return $ownerId === $userId;
        }

        return false;
    }
}
