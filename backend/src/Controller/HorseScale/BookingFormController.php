<?php

namespace App\Controller\HorseScale;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class BookingFormController extends AbstractController
{
    #[Route('/horsescale/book', name: 'horsescale_booking_form', methods: ['GET', 'POST'])]
    public function __invoke(Request $request): Response
    {
        if ($request->isMethod('POST')) {
            // Here one would normally persist the booking request
            $this->addFlash('success', 'Booking request received.');

            return $this->redirectToRoute('horsescale_booking_form');
        }

        return $this->render('horsescale/booking_form.html.twig');
    }
}
