<?php

namespace Flus\models;

class Invoice {
    const INVOICES_PATH = DATA_PATH . '/extensions-data/xExtension-Flus/invoices';

    public static function generateInvoiceNumber() {
        $lock_path = self::INVOICES_PATH . '/.lock';

        $lock_file = fopen($lock_path, 'r+');

        $invoice_number = null;

        if (flock($lock_file, LOCK_EX)) {
            $last_invoice_number = @fread($lock_file, filesize($lock_path));
            $invoice_number = self::getNextInvoiceNumber($last_invoice_number);

            rewind($lock_file);
            fwrite($lock_file, $invoice_number);

            flock($lock_file, LOCK_UN);
        }

        fclose($lock_file);

        return $invoice_number;
    }

    private static function getNextInvoiceNumber($last_invoice_number) {
        $current_date = getdate();
        $year = $current_date['year'];
        $month = $current_date['mon'];

        $invoice_sequence = 1;
        if ($last_invoice_number) {
            list(
                $last_invoice_year,
                $last_invoice_month,
                $last_invoice_sequence
            ) = array_map('intval', explode('-', $last_invoice_number));

            if ($last_invoice_year === $year) {
                $invoice_sequence = $last_invoice_sequence + 1;
            }
        }

        $invoice_format = '%04d-%02d-%04d';
        return sprintf(
            $invoice_format, $year, $month, $invoice_sequence
        );
    }

    public function __construct($invoice_number, $payment_service) {
        $this->invoice_number = $invoice_number;
        $this->delivery_date = timestamptodate($payment_service->date(), false);
        $this->client_username = $payment_service->username();
        $this->address = $payment_service->address();
        $this->amount = $payment_service->amount();
        $this->frequency = $payment_service->frequency();
    }

    public function saveAsPdf() {
        $pdf = new InvoicePdf();
        $pdf->addLogo('https://flus.io/carnet/logo.png');
        $pdf->addInvoiceInformation([
            'N° facture' => $this->invoice_number,
            'Date' => $this->delivery_date,
            'Identifiant client' => $this->client_username,
        ]);
        $pdf->addClientInformation([
            $this->address['first_name'] . ' ' . $this->address['last_name'],
            $this->address['address1'],
            $this->address['postcode'] . ' ' . $this->address['city'],
        ]);
        if ($this->frequency === 'month') {
            $period = '1 mois';
        } else {
            $period = '1 an';
        }
        $pdf->addPurchases([
            [
                'description' => "Renouvellement d'un abonnement\nde " . $period . " à flus.io",
                'number' => 1,
                'price' => $this->amount . ' €',
                'total' => $this->amount . ' €',
            ],
        ]);
        $pdf->addTotalPurchases([
            'ht' => $this->amount . ' €',
            'tva' => 'non applicable',
            'ttc' => $this->amount . ' €',
        ]);
        $pdf->addFooter([
            'Marien Fressinaud Mas de Feix / Flus – 57 rue du Vercors, 38000 Grenoble – support@flus.io',
            'micro-entreprise – N° Siret 878 196 278 00013 – 878 196 278 R.C.S. Grenoble',
            'TVA non applicable, art. 293 B du CGI',
        ]);

        $invoice_filepath = self::INVOICES_PATH . '/facture-' . $this->invoice_number . '.pdf';
        $pdf->save($invoice_filepath);
    }
}

class InvoicePdf extends \FPDF {
    private $footer_infos = [];

    public function __construct() {
        parent::__construct();

        $this->AddPage();
        $this->SetFont('helvetica', '', 12);
        $this->SetFillColor(225);
    }

    public function save($filepath) {
        $this->Output('F', $filepath);
    }

    public function addLogo($path) {
        $this->Image($path, 20, 20, 60);
    }

    public function addInvoiceInformation($infos) {
        $this->SetY(20);
        foreach ($infos as $info_key => $info_value) {
            $this->SetX(-100);
            $this->SetFont('', '');
            $this->Cell(40, 10, $this->pdf_decode($info_key), 0, 0);
            $this->SetFont('', 'B');
            $this->Cell(0, 10, $this->pdf_decode($info_value), 0, 1);
        }
    }

    public function addClientInformation($infos) {
        $this->SetY(50);
        $this->SetX(-100);

        $this->SetFont('', '');
        $this->Cell(0, 10, 'Adresse client', 0, 1);

        $this->SetFont('', 'B');
        foreach ($infos as $info) {
            $this->SetX(-100);
            $this->Cell(0, 5, $this->pdf_decode($info), 0, 1);
        }
    }

    public function addPurchases($purchases) {
        $this->SetXY(20, 110);
        $this->SetFont('', 'B');
        $this->Cell(90, 10, 'Description', 0, 0, '', true);
        $this->Cell(25, 10, $this->pdf_decode('Quantité'), 0, 0, '', true);
        $this->Cell(25, 10, 'Prix HT', 0, 0, '', true);
        $this->Cell(25, 10, 'Total', 0, 1, '', true);

        $this->SetFont('', '');
        $this->SetXY(20, $this->GetY() + 5);
        foreach ($purchases as $purchase) {
            $this->MultiCell(90, 5, $this->pdf_decode($purchase['description']), 0);

            $this->SetXY(110, $this->GetY() - 10);
            $this->Cell(25, 5, $this->pdf_decode($purchase['number']), 0, 0);
            $this->Cell(25, 5, $this->pdf_decode($purchase['price']), 0, 0);
            $this->Cell(25, 5, $this->pdf_decode($purchase['total']), 0, 1);

            $this->SetXY(20, $this->GetY() + 10);
        }
    }

    public function addTotalPurchases($infos) {
        $this->SetY($this->GetY() + 20);
        $this->SetFont('', 'B');

        $this->SetX(135);
        $this->Cell(25, 10, 'Prix HT', 0, 0, '', true);
        $this->Cell(25, 10, $this->pdf_decode($infos['ht']), 0, 1);

        $this->SetX(135);
        $this->Cell(25, 10, 'TVA', 0, 0, '', true);
        $this->Cell(25, 10, $this->pdf_decode($infos['tva']), 0, 1);

        $this->SetX(135);
        $this->Cell(25, 10, 'Total TTC', 0, 0, '', true);
        $this->Cell(25, 10, $this->pdf_decode($infos['ttc']), 0, 1);
    }

    public function addFooter($infos) {
        $this->footer_infos = $infos;
    }

    public function Footer() {
        $offset = count($this->footer_infos) * 5 + 20;
        $this->SetY(-$offset);
        $this->SetFont('', 'I', 10);
        foreach ($this->footer_infos as $info) {
            $this->Cell(0, 5, $this->pdf_decode($info), 0, 1, 'C');
        }
    }

    private function pdf_decode($string) {
        return mb_convert_encoding($string, 'windows-1252', 'utf-8');
    }
}
