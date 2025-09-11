<?php

namespace App\Controller;

use App\Entity\User;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Core\Security;
use Symfony\Contracts\Translation\TranslatorInterface;

class MeController extends AbstractController
{
    public function __construct(private TranslatorInterface $translator)
    {
    }
    #[Route('/api/me', name: 'api_me', methods: ['GET'])]
    public function __invoke(Security $security): JsonResponse
    {
        /** @var User|null $user */
        $user = $security->getUser();

        if (!$user instanceof User) {
            return $this->json(['message' => $this->translator->trans('Unauthorized', [], 'validators')], 401);
        }

        $data = [
            'id' => $user->getId(),
            'email' => $user->getEmail(),
            'roles' => $user->getRoles(),
        ];

        if (method_exists($user, 'getFirstName')) {
            $data['firstName'] = $user->getFirstName();
        }
        if (method_exists($user, 'getLastName')) {
            $data['lastName'] = $user->getLastName();
        }
        if (method_exists($user, 'getAssignedStallUnit')) {
            $stallUnit = $user->getAssignedStallUnit();
            if ($stallUnit) {
                $label = null;
                if (method_exists($stallUnit, 'getLabel')) {
                    $label = $stallUnit->getLabel();
                } elseif (method_exists($stallUnit, 'getName')) {
                    $label = $stallUnit->getName();
                }
                $data['assignedStallUnit'] = [
                    'id' => $stallUnit->getId(),
                    'label' => $label,
                ];
            }
        }

        return $this->json($data);
    }
}
