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

class SubscriptionController extends AbstractController
{
    #[Route('/api/subscriptions', methods: ['GET'])]
    #[IsGranted('ROLE_ADMIN')]
    public function list(SubscriptionRepository $subscriptionRepository): JsonResponse
    {
        $subscriptions = $subscriptionRepository->findAll();
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
        if (!is_array($data) || !isset($data['type'], $data['title'], $data['amount'], $data['startsAt'], $data['interval'])) {
            return $this->json(['message' => 'Invalid payload'], 400);
        }

        $relations = array_intersect_key($data, array_flip(['userId', 'horseId', 'stallUnitId']));
        $provided = array_filter($relations, static fn($v) => $v !== null && $v !== '');
        if (count($provided) !== 1) {
            return $this->json(['message' => 'Exactly one relation required'], 400);
        }

        try {
            $type = SubscriptionType::from($data['type']);
            $interval = SubscriptionInterval::from($data['interval']);
        } catch (\ValueError) {
            return $this->json(['message' => 'Invalid type or interval'], 400);
        }

        $subscription = new Subscription();
        $subscription->setSubscriptionType($type)
            ->setTitle($data['title'])
            ->setDescription($data['description'] ?? null)
            ->setAmount($data['amount'])
            ->setStartsAt(new \DateTimeImmutable($data['startsAt']))
            ->setNextDue(new \DateTimeImmutable($data['nextDue'] ?? $data['startsAt']))
            ->setInterval($interval)
            ->setActive($data['active'] ?? true)
            ->setAutoRenew($data['autoRenew'] ?? true);

        if ($type === SubscriptionType::USER && isset($data['userId'])) {
            /** @var User|null $user */
            $user = $em->getRepository(User::class)->find($data['userId']);
            if (!$user) {
                return $this->json(['message' => 'User not found'], 404);
            }
            $subscription->setUser($user);
        } elseif ($type === SubscriptionType::HORSE && isset($data['horseId'])) {
            /** @var Horse|null $horse */
            $horse = $em->getRepository(Horse::class)->find($data['horseId']);
            if (!$horse) {
                return $this->json(['message' => 'Horse not found'], 404);
            }
            $subscription->setHorse($horse);
            $subscription->setUser($horse->getOwner());
        } elseif ($type === SubscriptionType::STALL && isset($data['stallUnitId'])) {
            /** @var StallUnit|null $stall */
            $stall = $em->getRepository(StallUnit::class)->find($data['stallUnitId']);
            if (!$stall) {
                return $this->json(['message' => 'Stall unit not found'], 404);
            }
            $subscription->setStallUnit($stall);
            $horse = method_exists($stall, 'getCurrentHorse') ? $stall->getCurrentHorse() : null;
            $user = $horse?->getOwner();
            if (!$user) {
                return $this->json(['message' => 'User not found'], 404);
            }
            $subscription->setHorse($horse);
            $subscription->setUser($user);
        } else {
            return $this->json(['message' => 'Relation mismatch'], 400);
        }

        $em->persist($subscription);
        $em->flush();

        return $this->json($this->serializeSubscription($subscription), 201);
    }

    private function serializeSubscription(Subscription $subscription): array
    {
        return [
            'id' => $subscription->getId(),
            'type' => $subscription->getSubscriptionType()->value,
            'userId' => $subscription->getUser()->getId(),
            'horseId' => $subscription->getHorse()?->getId(),
            'stallUnitId' => $subscription->getStallUnit()?->getId(),
            'title' => $subscription->getTitle(),
            'description' => $subscription->getDescription(),
            'amount' => $subscription->getAmount(),
            'startsAt' => $subscription->getStartsAt()->format('c'),
            'nextDue' => $subscription->getNextDue()->format('c'),
            'interval' => $subscription->getInterval()->value,
            'active' => $subscription->isActive(),
            'autoRenew' => $subscription->isAutoRenew(),
        ];
    }
}
