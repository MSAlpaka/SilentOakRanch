<?php

namespace App\Tests;

use App\Entity\Booking;
use App\Entity\Documentation;
use App\Enum\DocumentationType;
use App\Service\DocumentationService;
use App\Service\PdfGenerator;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;

class DocumentationServiceTest extends TestCase
{
    private function createService(?EntityManagerInterface $em = null, ?PdfGenerator $pdf = null): DocumentationService
    {
        $em = $em ?? $this->createMock(EntityManagerInterface::class);
        $pdf = $pdf ?? $this->createStub(PdfGenerator::class);
        return new DocumentationService($em, $pdf);
    }

    public function testCreateDocumentationBasisPersists(): void
    {
        $booking = new Booking();
        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects($this->once())->method('persist')->with($this->isInstanceOf(Documentation::class));
        $em->expects($this->once())->method('flush');
        $service = $this->createService($em);

        $doc = $service->createDocumentation($booking, DocumentationType::BASIS, ['notes' => 'hello']);

        $this->assertSame('hello', $doc->getNotes());
        $this->assertSame(DocumentationType::BASIS, $doc->getType());
        $this->assertNull($doc->getPhotos());
    }

    public function testCreateDocumentationBasisRejectsPhotos(): void
    {
        $booking = new Booking();
        $service = $this->createService();

        $this->expectException(\InvalidArgumentException::class);

        $service->createDocumentation($booking, DocumentationType::BASIS, [
            'notes' => 'text',
            'photos' => ['a.jpg'],
        ]);
    }

    public function testCreateDocumentationStandardMissingPhotosThrows(): void
    {
        $booking = new Booking();
        $service = $this->createService();

        $this->expectException(\InvalidArgumentException::class);

        $service->createDocumentation($booking, DocumentationType::STANDARD, [
            'notes' => 'text',
        ]);
    }

    public function testCreateDocumentationPremiumMissingVideosThrows(): void
    {
        $booking = new Booking();
        $service = $this->createService();

        $this->expectException(\InvalidArgumentException::class);

        $service->createDocumentation($booking, DocumentationType::PREMIUM, [
            'notes' => 'text',
            'photos' => ['p.jpg'],
            // missing videos
            'metrics' => ['speed' => 10],
        ]);
    }

    public function testExportPremiumGeneratesPdf(): void
    {
        $booking = new Booking();
        $pdf = $this->createMock(PdfGenerator::class);
        $pdf->method('generatePdf')->willReturn('%PDF-1.4 mock');
        $service = $this->createService(null, $pdf);

        $doc = (new Documentation())
            ->setBooking($booking)
            ->setType(DocumentationType::PREMIUM)
            ->setNotes('text')
            ->setPhotos(['p.jpg'])
            ->setVideos(['v.mp4'])
            ->setMetrics(['speed' => 10]);

        $content = $service->export($doc);
        $this->assertStringStartsWith('%PDF', $content);
    }

    public function testExportNonPremiumThrows(): void
    {
        $booking = new Booking();
        $service = $this->createService();

        $doc = (new Documentation())
            ->setBooking($booking)
            ->setType(DocumentationType::BASIS)
            ->setNotes('text');

        $this->expectException(\RuntimeException::class);
        $service->export($doc);
    }
}
