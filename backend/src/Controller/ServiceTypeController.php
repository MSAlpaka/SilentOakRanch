<?php

namespace App\Controller;

use App\Entity\ServiceType;
use App\Enum\ServiceProviderType;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Contracts\Translation\TranslatorInterface;

class ServiceTypeController extends AbstractController
{
    public function __construct(private TranslatorInterface $translator)
    {
    }

    #[Route('/api/service-types', methods: ['GET'])]
    #[IsGranted('ROLE_USER')]
    public function list(EntityManagerInterface $em): JsonResponse
    {
        $types = $em->getRepository(ServiceType::class)->findAll();

        $data = array_map(fn (ServiceType $type) => [
            'id' => $type->getId(),
            'providerType' => $type->getProviderType()->value,
            'name' => $type->getName(),
            'defaultDurationMinutes' => $type->getDefaultDurationMinutes(),
            'basePrice' => $type->getBasePrice(),
            'taxable' => $type->isTaxable(),
        ], $types);

        return $this->json($data);
    }

    #[Route('/api/service-types', methods: ['POST'])]
    #[IsGranted('ROLE_ADMIN')]
    public function create(Request $request, EntityManagerInterface $em): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        if (!is_array($data) || !isset($data['providerType'], $data['name'], $data['defaultDurationMinutes'], $data['basePrice'], $data['taxable'])) {
            return $this->json(['message' => $this->translator->trans('Invalid payload', [], 'validators')], 400);
        }

        try {
            $providerType = ServiceProviderType::from($data['providerType']);
        } catch (\ValueError) {
            return $this->json(['message' => $this->translator->trans('Invalid provider type', [], 'validators')], 400);
        }

        $serviceType = (new ServiceType())
            ->setProviderType($providerType)
            ->setName($data['name'])
            ->setDefaultDurationMinutes((int) $data['defaultDurationMinutes'])
            ->setBasePrice($data['basePrice'])
            ->setTaxable((bool) $data['taxable']);

        $em->persist($serviceType);
        $em->flush();

        return $this->json([
            'id' => $serviceType->getId(),
            'providerType' => $serviceType->getProviderType()->value,
            'name' => $serviceType->getName(),
            'defaultDurationMinutes' => $serviceType->getDefaultDurationMinutes(),
            'basePrice' => $serviceType->getBasePrice(),
            'taxable' => $serviceType->isTaxable(),
        ], 201);
    }
}
