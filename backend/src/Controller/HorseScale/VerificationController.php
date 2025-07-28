<?php

namespace App\Controller\HorseScale;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use App\Repository\ScaleBookingRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Request;

class VerificationController extends AbstractController
{

#[Route('/horsescale/verify', name: 'horsescale_verify', methods: ['GET', 'POST'])]
    public function __invoke(Request $request, ScaleBookingRepository $repository, EntityManagerInterface $em): Response
    {
        $code = $request->get('code');
        $status = 'invalid';
        $booking = null;

        if ($code) {
            $booking = $repository->findOneBy(['qrCode' => $code]);

            if ($booking) {
                if ($booking->getRedeemedAt()) {
                    $status = 'redeemed';
                } else {
                    $status = 'valid';

                    if ($request->isMethod('POST')) {
                        $booking->setRedeemedAt(new \DateTimeImmutable());
                        $em->flush();

                        return $this->redirectToRoute('horsescale_verify', ['code' => $code]);
                    }
                }
            }
        }

        return $this->render('horsescale/verification.html.twig', [
            'code' => $code,
            'status' => $status,
        ]);
    }
}
