<?php

namespace App\Controller\Api;

use App\Repository\InvoiceRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Contracts\Translation\TranslatorInterface;

class InvoiceController extends AbstractController
{
    public function __construct(private TranslatorInterface $translator)
    {
    }

    #[Route('/api/invoices', methods: ['GET'])]
    public function list(Security $security, InvoiceRepository $invoiceRepository): JsonResponse
    {
        $user = $security->getUser();
        if (!$user) {
            return $this->json(['message' => $this->translator->trans('Unauthorized', [], 'validators')], 401);
        }

        $invoices = $invoiceRepository->findBy(['user' => $user], ['createdAt' => 'DESC']);
        $data = [];
        foreach ($invoices as $invoice) {
            $data[] = [
                'id' => $invoice->getId(),
                'createdAt' => $invoice->getCreatedAt()->format('c'),
                'amount' => $invoice->getAmount(),
                'status' => $invoice->getStatus()->name,
                'downloadUrl' => '/api/invoices/' . $invoice->getId(),
            ];
        }

        return $this->json($data);
    }

    #[Route('/api/invoices/{id}', methods: ['GET'])]
    public function download(int $id, InvoiceRepository $invoiceRepository, Security $security): Response
    {
        $invoice = $invoiceRepository->find($id);
        if (!$invoice) {
            return $this->json(['message' => $this->translator->trans('Not found', [], 'validators')], 404);
        }

        $user = $security->getUser();
        if (!$user) {
            return $this->json(['message' => $this->translator->trans('Unauthorized', [], 'validators')], 401);
        }

        if ($invoice->getUser() !== $user && !$security->isGranted('ROLE_ADMIN')) {
            return $this->json(['message' => $this->translator->trans('Forbidden', [], 'validators')], 403);
        }

        $pdfPath = $invoice->getPdfPath();
        if (!$pdfPath || !is_file($pdfPath)) {
            return $this->json(['message' => $this->translator->trans('File not found', [], 'validators')], 404);
        }

        $response = new BinaryFileResponse($pdfPath);
        $response->setContentDisposition(ResponseHeaderBag::DISPOSITION_INLINE, basename($pdfPath));
        return $response;
    }
}
