<?php

namespace App\Controller;

use App\Entity\Booking;
use App\Entity\Horse;
use App\Repository\BookingRepository;
use App\Repository\StallUnitRepository;
use App\Repository\PricingRuleRepository;
use App\Repository\PackageRepository;
use App\Repository\AddOnRepository;
use App\Service\CalendarService;
use App\Service\BookingService;
use App\Enum\PricingUnit;
use App\Enum\BookingType;
use App\Entity\StallUnit;
use App\Entity\User;
use Symfony\Component\Security\Http\Attribute\IsGranted;
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
        Security $security,
        PricingRuleRepository $pricingRuleRepository
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

        // populate new booking fields with defaults
        $booking->setType(BookingType::OTHER)
            ->setLabel('Stall booking')
            ->setDateFrom($start)
            ->setDateTo($end)
            ->setIsConfirmed(false);

        if (isset($data['price'])) {
            $booking->setPrice($data['price']);
        } else {
            $rule = $pricingRuleRepository->findDefault(BookingType::OTHER, $start);
            if ($rule) {
                $price = (float) $rule->getPrice();
                $cost = $price;
                $unit = $rule->getUnit();
                if ($unit === PricingUnit::PER_DAY) {
                    $days = max(1, $start->diff($end)->days);
                    $cost = $price * $days;
                } elseif ($unit === PricingUnit::PER_MONTH) {
                    $diff = $start->diff($end);
                    $months = $diff->y * 12 + $diff->m + $diff->d / 30;
                    $cost = $price * $months;
                }
                $booking->setPrice(number_format($cost, 2, '.', ''));
            }
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
            'price' => $booking->getPrice(),
        ]);
    }

    #[Route('/api/horse/bookings', name: 'api_create_horse_booking', methods: ['POST'])]
    #[IsGranted('ROLE_USER')]
    public function createHorseBooking(
        Request $request,
        EntityManagerInterface $em,
        Security $security,
        StallUnitRepository $stallUnitRepository,
        PricingRuleRepository $pricingRuleRepository
    ): JsonResponse {
        $data = json_decode($request->getContent(), true);
        if (!isset($data['horseId'], $data['type'], $data['label'], $data['dateFrom'])) {
            return $this->json(['message' => 'Invalid payload'], 400);
        }

        /** @var User|null $user */
        $user = $security->getUser();
        if (!$user instanceof User) {
            return $this->json(['message' => 'Unauthorized'], 401);
        }

        /** @var Horse|null $horse */
        $horse = $em->getRepository(Horse::class)->find($data['horseId']);
        if (!$horse) {
            return $this->json(['message' => 'Horse not found'], 404);
        }

        if ($horse->getOwner() !== $user) {
            return $this->json(['message' => 'Forbidden'], 403);
        }

        $booking = new Booking();
        $booking->setHorse($horse)
            ->setUser($user->getEmail())
            ->setType(BookingType::from($data['type']))
            ->setLabel($data['label']);

        $from = new \DateTimeImmutable($data['dateFrom']);
        $booking->setDateFrom($from)
            ->setStartDate($from);

        if (!empty($data['dateTo'])) {
            $to = new \DateTimeImmutable($data['dateTo']);
            $booking->setDateTo($to)
                ->setEndDate($to);
        } else {
            $booking->setEndDate($from);
        }

        $stallUnit = $horse->getCurrentLocation();
        if (!$stallUnit instanceof StallUnit) {
            $stallUnit = $stallUnitRepository->findOneBy([]);
            if (!$stallUnit) {
                return $this->json(['message' => 'No stall unit available'], 400);
            }
        }
        $booking->setStallUnit($stallUnit);

        if (isset($data['price'])) {
            $booking->setPrice($data['price']);
        } else {
            $rule = $pricingRuleRepository->findDefault($booking->getType(), $booking->getDateFrom());
            if ($rule) {
                $price = (float) $rule->getPrice();
                $cost = $price;
                $unit = $rule->getUnit();
                $dateTo = $booking->getDateTo() ?? $booking->getDateFrom();
                if ($unit === PricingUnit::PER_DAY) {
                    $days = max(1, $booking->getDateFrom()->diff($dateTo)->days);
                    $cost = $price * $days;
                } elseif ($unit === PricingUnit::PER_MONTH) {
                    $diff = $booking->getDateFrom()->diff($dateTo);
                    $months = $diff->y * 12 + $diff->m + $diff->d / 30;
                    $cost = $price * $months;
                }
                $booking->setPrice(number_format($cost, 2, '.', ''));
            }
        }

        $em->persist($booking);
        $em->flush();

        return $this->json([
            'id' => $booking->getId(),
            'horseId' => $horse->getId(),
            'type' => $booking->getType()->value,
            'label' => $booking->getLabel(),
            'dateFrom' => $booking->getDateFrom()->format('c'),
            'dateTo' => $booking->getDateTo()?->format('c'),
            'isConfirmed' => $booking->isConfirmed(),
            'price' => $booking->getPrice(),
        ], 201);
    }
    #[Route('/api/package-bookings/check', name: 'api_package_booking_check', methods: ['POST'])]
    public function checkAvailability(Request $request, CalendarService $calendarService): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        if (!isset($data['startDate'], $data['endDate'])) {
            return $this->json(['message' => 'Invalid payload'], 400);
        }
        $start = new \DateTimeImmutable($data['startDate']);
        $end = new \DateTimeImmutable($data['endDate']);
        $available = $calendarService->isRangeFree($start, $end);

        return $this->json(['available' => $available]);
    }

    #[Route('/api/package-bookings', name: 'api_package_booking_create', methods: ['POST'])]
    public function createPackageBooking(
        Request $request,
        PackageRepository $packageRepository,
        AddOnRepository $addOnRepository,
        BookingService $bookingService
    ): JsonResponse {
        $data = json_decode($request->getContent(), true);
        if (!isset($data['packageId'], $data['startDate'])) {
            return $this->json(['message' => 'Invalid payload'], 400);
        }

        $package = $packageRepository->find($data['packageId']);
        if (!$package) {
            return $this->json(['message' => 'Package not found'], 404);
        }

        $start = new \DateTimeImmutable($data['startDate']);
        $addOns = [];
        if (!empty($data['addOnIds']) && is_array($data['addOnIds'])) {
            $addOns = $addOnRepository->findBy(['id' => $data['addOnIds']]);
        }

        try {
            $booking = $bookingService->createBooking($package, $start, $addOns);
        } catch (\RuntimeException $e) {
            return $this->json(['message' => $e->getMessage()], 400);
        }

        return $this->json([
            'id' => $booking->getId(),
            'startDate' => $booking->getStartDate()->format('c'),
            'endDate' => $booking->getEndDate()->format('c'),
            'price' => $booking->getPrice(),
            'package' => [
                'id' => $package->getId(),
                'name' => $package->getName(),
            ],
            'addOns' => array_map(fn($a) => ['id' => $a->getId(), 'name' => $a->getName()], $booking->getAddOns()->toArray()),
        ], 201);
    }
}
