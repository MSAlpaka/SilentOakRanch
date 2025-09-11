<?php

namespace App\Service;

use App\Entity\Booking;
use App\Entity\Invoice;
use App\Entity\InvoiceItem;
use App\Entity\ScaleBooking;
use App\Entity\User;
use App\Enum\InvoiceStatus;
use Doctrine\ORM\EntityManagerInterface;

class InvoiceService
{
    private const TAX_RATE = 0.19;

    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly PdfGenerator $pdfGenerator,
        private readonly MailService $mailService,
        private readonly string $projectDir
    ) {
    }

    public function createFromStripePayment(Booking|ScaleBooking $booking, string $stripePaymentId): Invoice
    {
        if ($booking instanceof Booking) {
            $user = $this->em->getRepository(User::class)->findOneBy(['email' => $booking->getUser()]);
            if (!$user) {
                throw new \RuntimeException('User not found for booking');
            }
            $label = $booking->getLabel();
            $amount = (float) $booking->getPrice();
        } else {
            $user = $booking->getOwner();
            $label = sprintf('Scale booking %s', $booking->getHorse()->getName());
            $amount = (float) $booking->getPrice();
        }

        $gross = $amount;
        $net = $gross / (1 + self::TAX_RATE);
        $tax = $gross - $net;

        $invoice = new Invoice();
        $invoice->setUser($user)
            ->setStripePaymentId($stripePaymentId)
            ->setStatus(InvoiceStatus::PAID)
            ->setAmount(number_format($gross, 2, '.', ''))
            ->setCurrency('USD');

        if ($booking instanceof Booking) {
            $invoice->setBooking($booking);
        } else {
            $invoice->setScaleBooking($booking);
        }

        $this->em->persist($invoice);

        $item = new InvoiceItem();
        $item->setInvoice($invoice)
            ->setLabel($label)
            ->setAmount(number_format($gross, 2, '.', ''));
        $this->em->persist($item);

        $this->em->flush();

        $pdfPath = $this->generatePdf($invoice, $user, [[
            'label' => $label,
            'amount' => $gross,
        ]], $net, $tax, $gross);
        $invoice->setPdfPath($pdfPath);

        $this->em->flush();

        $this->mailService->sendInvoice($invoice);

        return $invoice;
    }

    /**
     * @param array<int,array{label:string,amount:float}> $items
     */
    private function generatePdf(Invoice $invoice, User $user, array $items, float $net, float $tax, float $gross): string
    {
        $logo = 'https://via.placeholder.com/150x50?text=Logo';
        $sender = 'Silent Oak Ranch<br>123 Farm Lane<br>City, Country';

        $itemsHtml = '';
        foreach ($items as $line) {
            $itemsHtml .= sprintf(
                '<tr><td>%s</td><td style="text-align:right">%0.2f</td></tr>',
                htmlspecialchars($line['label'], ENT_QUOTES),
                $line['amount']
            );
        }

        $netFormatted = number_format($net, 2, '.', '');
        $taxFormatted = number_format($tax, 2, '.', '');
        $grossFormatted = number_format($gross, 2, '.', '');

        $html = <<<HTML
<html>
<body>
<img src="$logo" alt="Logo" style="max-height:80px;"/>
<p>$sender</p>
<h1>Invoice #{$invoice->getId()}</h1>
<p><strong>Customer:</strong><br>{$user->getFirstName()} {$user->getLastName()}<br>{$user->getEmail()}</p>
<table width="100%" cellpadding="4" cellspacing="0" border="1">
<thead><tr><th align="left">Item</th><th align="right">Amount ({$invoice->getCurrency()})</th></tr></thead>
<tbody>
$itemsHtml
</tbody>
</table>
<p>Net: $netFormatted<br>Tax: $taxFormatted<br>Gross: $grossFormatted</p>
</body>
</html>
HTML;

        $pdfContent = $this->pdfGenerator->generatePdf($html);

        $dir = $this->projectDir . '/var/invoices';
        if (!is_dir($dir)) {
            mkdir($dir, 0777, true);
        }
        $file = sprintf('%s/invoice_%s.pdf', $dir, $invoice->getId());
        file_put_contents($file, $pdfContent);

        return $file;
    }
}
