<?php

namespace App\Controller;

use App\Entity\Appointment;
use App\Entity\Horse;
use App\Entity\ServiceProvider;
use App\Entity\ServiceType;
use App\Entity\User;
use App\Enum\AppointmentStatus;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Contracts\Translation\TranslatorInterface;
use App\Service\AppointmentService;

class AppointmentController extends AbstractController
{
    public function __construct(private TranslatorInterface $translator)
    {
    }

    #[Route('/api/appointments', methods: ['GET'])]
    #[IsGranted('ROLE_USER')]
    public function list(EntityManagerInterface $em, Security $security): JsonResponse
    {
        $user = $security->getUser();
        if (!$user instanceof User) {
            return $this->json(['message' => $this->translator->trans('Unauthorized', [], 'validators')], 401);
        }

        $repository = $em->getRepository(Appointment::class);
        if ($security->isGranted('ROLE_ADMIN') || $security->isGranted('ROLE_STAFF')) {
            $appointments = $repository->findAll();
        } else {
            $appointments = $repository->findBy(['owner' => $user]);
        }

        $data = array_map(fn (Appointment $a) => $this->serializeAppointment($a), $appointments);

        return $this->json($data);
    }

    #[Route('/api/appointments', methods: ['POST'])]
    #[IsGranted('ROLE_USER')]
    public function create(Request $request, EntityManagerInterface $em, Security $security): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        if (!is_array($data) || !isset($data['horseId'], $data['serviceTypeId'], $data['startTime'], $data['endTime'])) {
            return $this->json(['message' => $this->translator->trans('Invalid payload', [], 'validators')], 400);
        }

        /** @var Horse|null $horse */
        $horse = $em->getRepository(Horse::class)->find($data['horseId']);
        if (!$horse) {
            return $this->json(['message' => $this->translator->trans('Horse not found', [], 'validators')], 404);
        }

        $user = $security->getUser();
        if (!$user instanceof User) {
            return $this->json(['message' => $this->translator->trans('Unauthorized', [], 'validators')], 401);
        }
        $staff = $security->isGranted('ROLE_ADMIN') || $security->isGranted('ROLE_STAFF');
        if (!$staff && $horse->getOwner() !== $user) {
            return $this->json(['message' => $this->translator->trans('Forbidden', [], 'validators')], 403);
        }

        /** @var ServiceType|null $serviceType */
        $serviceType = $em->getRepository(ServiceType::class)->find($data['serviceTypeId']);
        if (!$serviceType) {
            return $this->json(['message' => $this->translator->trans('Service type not found', [], 'validators')], 404);
        }

        $serviceProvider = null;
        if (isset($data['serviceProviderId'])) {
            /** @var ServiceProvider|null $serviceProvider */
            $serviceProvider = $em->getRepository(ServiceProvider::class)->find($data['serviceProviderId']);
            if (!$serviceProvider) {
                return $this->json(['message' => $this->translator->trans('Service provider not found', [], 'validators')], 404);
            }
        }

        try {
            $start = new \DateTimeImmutable($data['startTime']);
            $end = new \DateTimeImmutable($data['endTime']);
        } catch (\Exception) {
            return $this->json(['message' => $this->translator->trans('Invalid date', [], 'validators')], 400);
        }

        $appointment = (new Appointment())
            ->setHorse($horse)
            ->setOwner($horse->getOwner())
            ->setServiceType($serviceType)
            ->setStartTime($start)
            ->setEndTime($end)
            ->setStatus(AppointmentStatus::REQUESTED);

        if ($serviceProvider) {
            $appointment->setServiceProvider($serviceProvider);
        }
        if (isset($data['price'])) {
            $appointment->setPrice($data['price']);
        }
        if (isset($data['notes'])) {
            $appointment->setNotes($data['notes']);
        }

        $em->persist($appointment);
        $em->flush();

        return $this->json($this->serializeAppointment($appointment), 201);
    }

    #[Route('/api/appointments/{id}/confirm', methods: ['POST'])]
    #[IsGranted('APPOINTMENT_EDIT', subject: 'appointment')]
    public function confirm(Appointment $appointment, EntityManagerInterface $em): JsonResponse
    {
        $appointment->setStatus(AppointmentStatus::CONFIRMED);
        $em->flush();

        return $this->json($this->serializeAppointment($appointment));
    }

    #[Route('/api/appointments/{id}/complete', methods: ['POST'])]
    #[IsGranted('APPOINTMENT_COMPLETE', subject: 'appointment')]
    public function complete(Appointment $appointment, Request $request, AppointmentService $appointmentService): JsonResponse
    {
        $createInvoice = $request->query->getBoolean('invoice', false);
        $appointmentService->complete($appointment, $createInvoice);

        return $this->json($this->serializeAppointment($appointment));
    }

    #[Route('/api/appointments/{id}/cancel', methods: ['POST'])]
    #[IsGranted('APPOINTMENT_EDIT', subject: 'appointment')]
    public function cancel(Appointment $appointment, EntityManagerInterface $em): JsonResponse
    {
        $appointment->setStatus(AppointmentStatus::CANCELED);
        $em->flush();

        return $this->json($this->serializeAppointment($appointment));
    }

    private function serializeAppointment(Appointment $appointment): array
    {
        $horse = $appointment->getHorse();
        $owner = $appointment->getOwner();
        $serviceType = $appointment->getServiceType();
        $provider = $appointment->getServiceProvider();
        $ownerName = trim($owner->getFirstName() . ' ' . $owner->getLastName());

        return [
            'id' => $appointment->getId(),
            'horse' => [
                'id' => $horse->getId(),
                'name' => $horse->getName(),
            ],
            'owner' => [
                'id' => $owner->getId(),
                'name' => $ownerName,
            ],
            'serviceType' => [
                'id' => $serviceType->getId(),
                'name' => $serviceType->getName(),
            ],
            'serviceProvider' => $provider ? [
                'id' => $provider->getId(),
                'name' => $provider->getName(),
            ] : null,
            'startTime' => $appointment->getStartTime()->format('c'),
            'endTime' => $appointment->getEndTime()->format('c'),
            'status' => $appointment->getStatus()->value,
            'price' => $appointment->getPrice(),
            'notes' => $appointment->getNotes(),
        ];
    }
}
