<?php

namespace App\Service;

use App\Entity\Booking;
use App\Entity\Documentation;
use App\Enum\DocumentationType;
use Doctrine\ORM\EntityManagerInterface;

class DocumentationService
{
    public function __construct(
        private EntityManagerInterface $em,
        private PdfGenerator $pdfGenerator
    ) {
    }

    public function createDocumentation(Booking $booking, DocumentationType $type, array $payload): Documentation
    {
        $required = ['notes'];
        $allowed = ['notes'];

        switch ($type) {
            case DocumentationType::STANDARD:
                $required[] = 'photos';
                $allowed[] = 'photos';
                break;
            case DocumentationType::PREMIUM:
                $required = ['notes', 'photos', 'videos', 'metrics'];
                $allowed = $required;
                break;
            case DocumentationType::BASIS:
            default:
                // only notes
                break;
        }

        foreach ($required as $field) {
            if (!array_key_exists($field, $payload)) {
                throw new \InvalidArgumentException(sprintf('Field "%s" is required.', $field));
            }
        }

        foreach ($payload as $key => $value) {
            if (!in_array($key, $allowed, true)) {
                throw new \InvalidArgumentException(sprintf('Field "%s" is not allowed for %s documentation.', $key, $type->value));
            }
        }

        $doc = new Documentation();
        $doc->setBooking($booking)
            ->setType($type)
            ->setNotes($payload['notes']);

        if (isset($payload['photos'])) {
            if (!is_array($payload['photos'])) {
                throw new \InvalidArgumentException('Photos must be an array.');
            }
            $doc->setPhotos($payload['photos']);
        }
        if (isset($payload['videos'])) {
            if (!is_array($payload['videos'])) {
                throw new \InvalidArgumentException('Videos must be an array.');
            }
            $doc->setVideos($payload['videos']);
        }
        if (isset($payload['metrics'])) {
            if (!is_array($payload['metrics'])) {
                throw new \InvalidArgumentException('Metrics must be an array.');
            }
            $doc->setMetrics($payload['metrics']);
        }

        $this->em->persist($doc);
        $this->em->flush();

        return $doc;
    }

    public function export(Documentation $doc): string
    {
        if ($doc->getType() !== DocumentationType::PREMIUM) {
            throw new \RuntimeException('Only premium documentation can be exported.');
        }

        $html = '<h1>Documentation</h1>';

        if ($doc->getNotes()) {
            $html .= '<p>' . htmlspecialchars($doc->getNotes(), ENT_QUOTES) . '</p>';
        }

        if ($photos = $doc->getPhotos()) {
            foreach ($photos as $photo) {
                $html .= sprintf('<img src="%s" alt="photo" />', htmlspecialchars((string) $photo, ENT_QUOTES));
            }
        }

        if ($videos = $doc->getVideos()) {
            $html .= '<ul>';
            foreach ($videos as $video) {
                $html .= sprintf('<li>%s</li>', htmlspecialchars((string) $video, ENT_QUOTES));
            }
            $html .= '</ul>';
        }

        if ($metrics = $doc->getMetrics()) {
            $html .= '<table><tbody>';
            foreach ($metrics as $key => $value) {
                $html .= sprintf('<tr><td>%s</td><td>%s</td></tr>',
                    htmlspecialchars((string) $key, ENT_QUOTES),
                    htmlspecialchars((string) $value, ENT_QUOTES)
                );
            }
            $html .= '</tbody></table>';
        }

        return $this->pdfGenerator->generatePdf($html);
    }
}
