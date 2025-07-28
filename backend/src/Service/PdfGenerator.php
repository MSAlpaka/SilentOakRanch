<?php

namespace App\Service;

use Dompdf\Dompdf;
use Dompdf\Options;

class PdfGenerator
{
    private Dompdf $dompdf;

    public function __construct()
    {
        $options = new Options();
        $options->set('isRemoteEnabled', true);
        $this->dompdf = new Dompdf($options);
    }

    public function generatePdf(string $html): string
    {
        $this->dompdf->loadHtml($html);
        $this->dompdf->render();
        return $this->dompdf->output();
    }
}
