<?php

namespace App\Controller\Api;

use App\Entity\Subscription;
use App\Entity\User;
use App\Entity\Horse;
use App\Entity\StallUnit;
use App\Enum\SubscriptionType;
use App\Enum\SubscriptionInterval;
use App\Repository\SubscriptionRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Contracts\Translation\TranslatorInterface;

class SubscriptionController extends AbstractController
{
    public function __construct(private TranslatorInterface $translator)
    {
    }
    #[Route('/api/subscriptions', methods: ['GET'])]
    #[IsGranted('ROLE_ADMIN')]
    public function list(Request $request, SubscriptionRepository $subscriptionRepository): JsonResponse
    {
        $typeParam = $request->query->get('type');
        $activeOnlyParam = $request->query->get('activeOnly');

        $type = null;
        if ($typeParam !== null) {
            try {
                $type = SubscriptionType::from(strtolower($typeParam));
            } catch (\ValueError) {
                return $this->json(['message' => $this->translator->trans('Invalid type', [], 'validators')], 400);
            }
        }

        $activeOnly = $activeOnlyParam === 'true';

        $subscriptions = $subscriptionRepository->findFiltered($type, $activeOnly);
        $data = [];
        foreach ($subscriptions as $subscription) {
            $data[] = $this->serializeSubscription($subscription);
        }

        return $this->json($data);
    }

    #[Route('/api/subscriptions', methods: ['POST'])]
    #[IsGranted('ROLE_ADMIN')]
    public function create(Request $request, EntityManagerInterface $em): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        if (!is_array($data)) {
            return $this->json(['message' => $this->translator->trans('Invalid payload', [], 'validators')], 400);
        }

        if (isset($data['startDate']) && !isset($data['startsAt'])) {
            $data['startsAt'] = $data['startDate'];
        }

        if (!isset($data['type'], $data['title'], $data['amount'], $data['startsAt'], $data['interval'])) {
            return $this->json(['message' => $this->translator->trans('Invalid payload', [], 'validators')], 400);
        }

        if (!is_numeric($data['amount']) || $data['amount'] <= 0) {
            return $this->json(['message' => $this->translator->trans('Amount must be greater than zero', [], 'validators')], 400);
        }

        try {
            $startsAt = new \DateTimeImmutable($data['startsAt']);
        } catch (\Exception) {
            return $this->json(['message' => $this->translator->trans('Invalid start date', [], 'validators')], 400);
        }

        $endDate = null;

        $autoRenew = $data['autoRenew'] ?? true;
        if ($autoRenew === false) {
            if (empty($data['endDate'])) {
                return $this->json(['message' => $this->translator->trans('endDate required when autoRenew is false', [], 'validators')], 400);
            }
            try {
                $endDate = new \DateTimeImmutable($data['endDate']);
            } catch (\Exception) {
                return $this->json(['message' => $this->translator->trans('Invalid endDate', [], 'validators')], 400);
            }
        }
        if (!empty($data['endDate']) && $autoRenew !== false) {
            try {
                $endDate = new \DateTimeImmutable($data['endDate']);
            } catch (\Exception) {
                return $this->json(['message' => $this->translator->trans('Invalid endDate', [], 'validators')], 400);
            }
        }
        $endDate = $endDate ?? null;

        $relations = array_intersect_key($data, array_flip(['userId', 'horseId', 'stallUnitId']));
        $provided = array_filter($relations, static fn($v) => $v !== null && $v !== '');
        if (count($provided) !== 1) {
            return $this->json(['message' => $this->translator->trans('Exactly one relation required', [], 'validators')], 400);
        }

        try {
            $type = SubscriptionType::from($data['type']);
            $interval = SubscriptionInterval::from($data['interval']);
        } catch (\ValueError) {
            return $this->json(['message' => $this->translator->trans('Invalid type or interval', [], 'validators')], 400);
        }

        $subscription = new Subscription();
        $nextDueStr = $data['nextDue'] ?? $data['startsAt'];
        $subscription->setSubscriptionType($type)
            ->setTitle($data['title'])
            ->setDescription($data['description'] ?? null)
            ->setAmount($data['amount'])
            ->setStartsAt($startsAt)
            ->setNextDue(new \DateTimeImmutable($nextDueStr))
            ->setEndDate($endDate)
            ->setInterval($interval)
            ->setActive($data['active'] ?? true)
            ->setAutoRenew($autoRenew);

        if ($type === SubscriptionType::USER && isset($data['userId'])) {
            /** @var User|null $user */
              $user = $em->getRepository(User::class)->find($data['userId']);
              if (!$user) {
                  return $this->json(['message' => $this->translator->trans('User not found', [], 'validators')], 404);
              }
            $subscription->setUser($user);
        } elseif ($type === SubscriptionType::HORSE && isset($data['horseId'])) {
            /** @var Horse|null $horse */
              $horse = $em->getRepository(Horse::class)->find($data['horseId']);
              if (!$horse) {
                  return $this->json(['message' => $this->translator->trans('Horse not found', [], 'validators')], 404);
              }
            $subscription->setHorse($horse);
            $subscription->setUser($horse->getOwner());
        } elseif ($type === SubscriptionType::STALL && isset($data['stallUnitId'])) {
            /** @var StallUnit|null $stall */
              $stall = $em->getRepository(StallUnit::class)->find($data['stallUnitId']);
              if (!$stall) {
                  return $this->json(['message' => $this->translator->trans('Stall unit not found', [], 'validators')], 404);
              }
            $subscription->setStallUnit($stall);
            $horse = method_exists($stall, 'getCurrentHorse') ? $stall->getCurrentHorse() : null;
            $user = $horse?->getOwner();
              if (!$user) {
                  return $this->json(['message' => $this->translator->trans('User not found', [], 'validators')], 404);
              }
          $subscription->setHorse($horse);
          $subscription->setUser($user);
        } else {
            return $this->json(['message' => $this->translator->trans('Relation mismatch', [], 'validators')], 400);
        }

        $em->persist($subscription);
        $em->flush();

        return $this->json($this->serializeSubscription($subscription), 201);
    }

    private function serializeSubscription(Subscription $subscription): array
    {
        $user = $subscription->getUser();
        $horse = $subscription->getHorse();
        $stall = $subscription->getStallUnit();

        return [
            'id' => $subscription->getId(),
            'type' => $subscription->getSubscriptionType()->value,
            'user' => [
                'id' => $user->getId(),
                'name' => trim($user->getFirstName() . ' ' . $user->getLastName()),
            ],
            'horse' => $horse ? [
                'id' => $horse->getId(),
                'name' => $horse->getName(),
            ] : null,
            'stallUnit' => $stall ? [
                'id' => $stall->getId(),
                'name' => method_exists($stall, 'getName') ? $stall->getName() : '',
            ] : null,
            'title' => $subscription->getTitle(),
            'description' => $subscription->getDescription(),
            'amount' => $subscription->getAmount(),
            'startsAt' => $subscription->getStartsAt()->format('c'),
            'nextDue' => $subscription->getNextDue()->format('c'),
            'endDate' => $subscription->getEndDate()?->format('c'),
            'interval' => $subscription->getInterval()->value,
            'active' => $subscription->isActive(),
            'autoRenew' => $subscription->isAutoRenew(),
        ];
    }
}
