<?php

namespace App\Controller;

use App\Message\ContactSubmission;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Routing\Annotation\Route;

class ContactController extends AbstractController
{
    public function __construct(private readonly MessageBusInterface $bus)
    {
    }

    #[Route('/api/contact', name: 'api_contact_submit', methods: ['POST'])]
    public function submit(Request $request): JsonResponse
    {
        $payload = json_decode($request->getContent(), true);
        if (!is_array($payload)) {
            return $this->json(['message' => 'Invalid payload'], Response::HTTP_BAD_REQUEST);
        }

        $name = isset($payload['name']) && is_string($payload['name']) ? trim($payload['name']) : null;
        $rawEmail = isset($payload['email']) && is_string($payload['email']) ? trim($payload['email']) : null;
        $message = isset($payload['message']) && is_string($payload['message']) ? trim($payload['message']) : null;

        if ($name === null || $name === '' || $rawEmail === null || $rawEmail === '' || $message === null || $message === '') {
            return $this->json(['message' => 'Invalid payload'], Response::HTTP_BAD_REQUEST);
        }

        $email = filter_var($rawEmail, FILTER_VALIDATE_EMAIL);
        if ($email === false) {
            return $this->json(['message' => 'Invalid payload'], Response::HTTP_BAD_REQUEST);
        }

        $submission = new ContactSubmission($name, $email, $message);
        $this->bus->dispatch($submission);

        return $this->json(['message' => 'Message sent']);
    }
}
