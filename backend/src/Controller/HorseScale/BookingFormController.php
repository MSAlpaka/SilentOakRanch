<?php

namespace App\Controller\HorseScale;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use App\Repository\ScaleBookingRepository;

class BookingFormController extends AbstractController
{
    #[Route('/horsescale/book', name: 'horsescale_booking_form', methods: ['GET', 'POST'])]
    public function __invoke(Request $request, ScaleBookingRepository $repository): Response
    {
        if ($request->isMethod('POST')) {
            $date = $request->request->get('date');
            $name = $request->request->get('name');

            if ($date && $name) {
                try {
                    $bookingDateTime = new \DateTimeImmutable($date);
                } catch (\Exception) {
                    $this->addFlash('error', 'Invalid date.');
                    return $this->redirectToRoute('horsescale_booking_form');
                }

                if ($repository->existsForDateTime($bookingDateTime)) {
                    $this->addFlash('error', 'Selected time slot is already booked.');
                } else {
                    // Here one would normally persist the booking request
                    $this->addFlash('success', 'Booking request received.');
                }

                return $this->redirectToRoute('horsescale_booking_form');
            }
        }

        return $this->render('horsescale/booking_form.html.twig');
    }
}
