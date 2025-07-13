<?php

namespace App\Controller;

use App\Repository\StallUnitRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;

class StallUnitController extends AbstractController
{
    #[Route('/api/stall-units', name: 'api_stall_units', methods: ['GET'])]
    public function __invoke(StallUnitRepository $stallUnitRepository): JsonResponse
    {
        $stallUnits = $stallUnitRepository->findAll();

        $data = [];
        foreach ($stallUnits as $stallUnit) {
            $label = null;
            if (method_exists($stallUnit, 'getLabel')) {
                $label = $stallUnit->getLabel();
            } elseif (method_exists($stallUnit, 'getName')) {
                $label = $stallUnit->getName();
            }

            $data[] = [
                'id' => $stallUnit->getId(),
                'label' => $label,
                'type' => $stallUnit->getType()->value,
                'status' => $stallUnit->getStatus()->value,
            ];
        }

        return $this->json($data);
    }
}
