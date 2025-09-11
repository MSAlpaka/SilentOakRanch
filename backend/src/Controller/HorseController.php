<?php

namespace App\Controller;

use App\Entity\Horse;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

class HorseController extends AbstractController
{
    #[Route('/api/horses', methods: ['GET'])]
    #[IsGranted('ROLE_USER')]
    public function list(EntityManagerInterface $em, Security $security): JsonResponse
    {
        $user = $security->getUser();
        if (!$user instanceof User) {
            return $this->json(['message' => 'Unauthorized'], 401);
        }

        $repository = $em->getRepository(Horse::class);
        if ($security->isGranted('ROLE_ADMIN') || $security->isGranted('ROLE_STAFF')) {
            $horses = $repository->findAll();
        } else {
            $horses = $repository->findBy(['owner' => $user]);
        }

        $data = array_map(fn (Horse $horse) => $this->serializeHorse($horse), $horses);

        return $this->json($data);
    }

    #[Route('/api/horses', methods: ['POST'])]
    #[IsGranted('ROLE_USER')]
    public function create(Request $request, EntityManagerInterface $em, Security $security): JsonResponse
    {
        $user = $security->getUser();
        if (!$user instanceof User) {
            return $this->json(['message' => 'Unauthorized'], 401);
        }

        $data = json_decode($request->getContent(), true);
        if (!is_array($data) || !isset($data['name'], $data['age'], $data['breed'])) {
            return $this->json(['message' => 'Invalid payload'], 400);
        }

        $owner = $user;
        if (isset($data['ownerId'])) {
            if ($security->isGranted('ROLE_ADMIN') || $security->isGranted('ROLE_STAFF')) {
                $owner = $em->getRepository(User::class)->find($data['ownerId']) ?? $owner;
            } elseif ($data['ownerId'] !== $user->getId()) {
                return $this->json(['message' => 'Forbidden'], 403);
            }
        }

        $horse = (new Horse())
            ->setName($data['name'])
            ->setAge((int) $data['age'])
            ->setBreed($data['breed'])
            ->setOwner($owner);

        if (isset($data['specialNotes'])) {
            $horse->setSpecialNotes($data['specialNotes']);
        }
        if (isset($data['medicalHistory'])) {
            $horse->setMedicalHistory($data['medicalHistory']);
        }
        if (isset($data['medication'])) {
            $horse->setMedication($data['medication']);
        }

        $em->persist($horse);
        $em->flush();

        return $this->json($this->serializeHorse($horse), 201);
    }

    #[Route('/api/horses/{id}', methods: ['PUT'])]
    #[IsGranted('ROLE_USER')]
    public function update(int $id, Request $request, EntityManagerInterface $em, Security $security): JsonResponse
    {
        /** @var Horse|null $horse */
        $horse = $em->getRepository(Horse::class)->find($id);
        if (!$horse) {
            return $this->json(['message' => 'Horse not found'], 404);
        }

        $user = $security->getUser();
        if (!$user instanceof User) {
            return $this->json(['message' => 'Unauthorized'], 401);
        }

        $staff = $security->isGranted('ROLE_ADMIN') || $security->isGranted('ROLE_STAFF');
        if (!$staff && $horse->getOwner() !== $user) {
            return $this->json(['message' => 'Forbidden'], 403);
        }

        $data = json_decode($request->getContent(), true);
        if (!is_array($data)) {
            return $this->json(['message' => 'Invalid payload'], 400);
        }

        if (isset($data['name'])) {
            $horse->setName($data['name']);
        }
        if (isset($data['age'])) {
            $horse->setAge((int) $data['age']);
        }
        if (isset($data['breed'])) {
            $horse->setBreed($data['breed']);
        }
        if (array_key_exists('specialNotes', $data)) {
            $horse->setSpecialNotes($data['specialNotes']);
        }
        if (array_key_exists('medicalHistory', $data)) {
            $horse->setMedicalHistory($data['medicalHistory']);
        }
        if (array_key_exists('medication', $data)) {
            $horse->setMedication($data['medication']);
        }
        if (isset($data['ownerId']) && $staff) {
            $owner = $em->getRepository(User::class)->find($data['ownerId']);
            if ($owner) {
                $horse->setOwner($owner);
            }
        }

        $em->flush();

        return $this->json($this->serializeHorse($horse));
    }

    #[Route('/api/horses/{id}', methods: ['DELETE'])]
    #[IsGranted('ROLE_USER')]
    public function delete(int $id, EntityManagerInterface $em, Security $security): JsonResponse
    {
        /** @var Horse|null $horse */
        $horse = $em->getRepository(Horse::class)->find($id);
        if (!$horse) {
            return $this->json(['message' => 'Horse not found'], 404);
        }

        $user = $security->getUser();
        if (!$user instanceof User) {
            return $this->json(['message' => 'Unauthorized'], 401);
        }

        $staff = $security->isGranted('ROLE_ADMIN') || $security->isGranted('ROLE_STAFF');
        if (!$staff && $horse->getOwner() !== $user) {
            return $this->json(['message' => 'Forbidden'], 403);
        }

        $em->remove($horse);
        $em->flush();

        return $this->json(null, 204);
    }

    private function serializeHorse(Horse $horse): array
    {
        $owner = $horse->getOwner();
        $ownerName = trim($owner->getFirstName() . ' ' . $owner->getLastName());

        return [
            'id' => $horse->getId(),
            'name' => $horse->getName(),
            'age' => $horse->getAge(),
            'breed' => $horse->getBreed(),
            'specialNotes' => $horse->getSpecialNotes(),
            'medicalHistory' => $horse->getMedicalHistory(),
            'medication' => $horse->getMedication(),
            'owner' => [
                'id' => $owner->getId(),
                'name' => $ownerName,
            ],
        ];
    }
}

