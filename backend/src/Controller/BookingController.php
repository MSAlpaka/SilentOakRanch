<?php

namespace App\Controller;

use App\Entity\Booking;
use App\Entity\Horse;
use App\Repository\BookingRepository;
use App\Repository\StallUnitRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Bundle\SecurityBundle\Security;

class BookingController extends AbstractController
{
    #[Route('/api/bookings', name: 'api_create_booking', methods: ['POST'])]
    public function __invoke(
        Request $request,
        StallUnitRepository $stallUnitRepository,
        BookingRepository $bookingRepository,
        EntityManagerInterface $em,
        Security $security
    ): JsonResponse {
        $data = json_decode($request->getContent(), true);
        if (!isset($data['stallUnitId'], $data['startDate'], $data['endDate'])) {
            return $this->json(['message' => 'Invalid payload'], 400);
        }

        $stallUnit = $stallUnitRepository->find($data['stallUnitId']);
        if (!$stallUnit) {
            return $this->json(['message' => 'StallUnit not found'], 404);
        }

        $start = new \DateTimeImmutable($data['startDate']);
        $end = new \DateTimeImmutable($data['endDate']);

        if ($bookingRepository->hasOverlap($stallUnit, $start, $end)) {
            return $this->json(['message' => 'Booking overlaps existing booking'], 400);
        }

        $booking = new Booking();
        $booking->setStallUnit($stallUnit)
            ->setStartDate($start)
            ->setEndDate($end);

        if (isset($data['horseId'])) {
            /** @var Horse|null $horse */
            $horse = $em->getRepository(Horse::class)->find($data['horseId']);
            if (!$horse) {
                return $this->json(['message' => 'Horse not found'], 404);
            }
            $booking->setHorse($horse);
        }

        $user = $security->getUser();
        if ($user && method_exists($user, 'getEmail')) {
            $booking->setUser($user->getEmail());
        } else {
            return $this->json(['message' => 'Unauthorized'], 401);
        }

        $em->persist($booking);
        $em->flush();

        $label = method_exists($stallUnit, 'getLabel') ? $stallUnit->getLabel() : $stallUnit->getName();

        return $this->json([
            'id' => $booking->getId(),
            'status' => $booking->getStatus()->name,
            'stallUnit' => [
                'id' => $stallUnit->getId(),
                'label' => $label,
            ],
            'startDate' => $start->format('c'),
            'endDate' => $end->format('c'),
        ]);
    }
}
