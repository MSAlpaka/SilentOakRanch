<?php

namespace App\Controller\HorseScale;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class VerificationController extends AbstractController
{
    #[Route('/horsescale/verify/{code}', name: 'horsescale_verify', methods: ['GET'])]
    public function __invoke(string $code): Response
    {
        // Normally the code would be looked up and validated
        return $this->render('horsescale/verification.html.twig', [
            'code' => $code,
        ]);
    }
}
