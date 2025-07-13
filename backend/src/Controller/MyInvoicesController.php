<?php

namespace App\Controller;

use App\Repository\InvoiceRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Bundle\SecurityBundle\Security;

class MyInvoicesController extends AbstractController
{
    #[Route('/api/my/invoices', name: 'api_my_invoices', methods: ['GET'])]
    public function __invoke(InvoiceRepository $invoiceRepository, Security $security): JsonResponse
    {
        $user = $security->getUser();

        if (!$user) {
            return $this->json(['message' => 'Unauthorized'], 401);
        }

        $invoices = $invoiceRepository->findByUser($user->getEmail());
        $data = [];

        foreach ($invoices as $invoice) {
            $number = method_exists($invoice, 'getNumber') ? $invoice->getNumber() : '';
            $data[] = [
                'id' => $invoice->getId(),
                'number' => $number,
                'period' => $invoice->getPeriod(),
                'amount' => $invoice->getTotal(),
                'status' => $invoice->getStatus()->name,
                'downloadUrl' => '/storage/invoices/' . $number . '.pdf',
            ];
        }

        return $this->json($data);
    }
}
