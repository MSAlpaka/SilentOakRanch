<?php

namespace App\Controller\HorseScale;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class AdminBookingController extends AbstractController
{
    #[Route('/horsescale/admin/bookings', name: 'horsescale_admin_bookings', methods: ['GET'])]
    public function __invoke(): Response
    {
        // In a real application bookings would be fetched from the database
        return $this->render('horsescale/admin_bookings.html.twig');
    }
}
