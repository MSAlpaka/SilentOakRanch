<?php

namespace App\Controller;

use App\Entity\ServiceProvider;
use App\Enum\ServiceProviderType;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Contracts\Translation\TranslatorInterface;

class ServiceProviderController extends AbstractController
{
    public function __construct(private TranslatorInterface $translator)
    {
    }

    #[Route('/api/service-providers', methods: ['GET'])]
    #[IsGranted('ROLE_USER')]
    public function list(EntityManagerInterface $em): JsonResponse
    {
        $providers = $em->getRepository(ServiceProvider::class)->findAll();

        $data = array_map(fn (ServiceProvider $provider) => [
            'id' => $provider->getId(),
            'name' => $provider->getName(),
            'type' => $provider->getType()->value,
            'contact' => $provider->getContact(),
            'notes' => $provider->getNotes(),
            'active' => $provider->isActive(),
        ], $providers);

        return $this->json($data);
    }

    #[Route('/api/service-providers', methods: ['POST'])]
    #[IsGranted('ROLE_ADMIN')]
    public function create(Request $request, EntityManagerInterface $em): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        if (!is_array($data) || !isset($data['name'], $data['type'], $data['contact'])) {
            return $this->json(['message' => $this->translator->trans('Invalid payload', [], 'validators')], 400);
        }

        try {
            $type = ServiceProviderType::from($data['type']);
        } catch (\ValueError) {
            return $this->json(['message' => $this->translator->trans('Invalid type', [], 'validators')], 400);
        }

        $provider = (new ServiceProvider())
            ->setName($data['name'])
            ->setType($type)
            ->setContact($data['contact']);

        if (isset($data['notes'])) {
            $provider->setNotes($data['notes']);
        }
        if (isset($data['active'])) {
            $provider->setActive((bool) $data['active']);
        }

        $em->persist($provider);
        $em->flush();

        return $this->json([
            'id' => $provider->getId(),
            'name' => $provider->getName(),
            'type' => $provider->getType()->value,
            'contact' => $provider->getContact(),
            'notes' => $provider->getNotes(),
            'active' => $provider->isActive(),
        ], 201);
    }
}
