<?php

namespace App\Controller;

use App\Entity\User;
use App\Repository\BookingRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;

class MyBookingsController extends AbstractController
{
    #[Route('/api/my/bookings', name: 'api_my_bookings', methods: ['GET'])]
    public function __invoke(BookingRepository $bookingRepository, Security $security): JsonResponse
    {
        /** @var User|null $user */
        $user = $security->getUser();

        if (!$user instanceof User) {
            return $this->json(['message' => 'Unauthorized'], 401);
        }

        $bookings = $bookingRepository->findByUser($user->getEmail());
        $data = [];

        foreach ($bookings as $booking) {
            $stallUnit = $booking->getStallUnit();

            $label = null;
            if (method_exists($stallUnit, 'getLabel')) {
                $label = $stallUnit->getLabel();
            } elseif (method_exists($stallUnit, 'getName')) {
                $label = $stallUnit->getName();
            }

            $horse = $booking->getHorse();
            $horseName = null;
            if ($horse && method_exists($horse, 'getName')) {
                $horseName = $horse->getName();
            }

            $data[] = [
                'stallUnit' => [
                    'label' => $label,
                ],
                'horse' => $horseName,
                'startDate' => $booking->getStartDate()->format('c'),
                'endDate' => $booking->getEndDate()->format('c'),
                'status' => $booking->getStatus()->name,
            ];
        }

        return $this->json($data);
    }
}

