<?php

namespace Application\Pdf;

use TCPDF;

/**
 * One page of a VL or EID result report: the dashboard logo and lab name on
 * top, and a line naming the dashboard at the bottom.
 */
class ResultPage extends TCPDF
{
    private string $logo = '';
    private string $lab = '';
    private string $footerText = '';

    public function setBranding(string $logo, string $lab, string $footerText): void
    {
        $this->logo = $logo;
        $this->lab = $lab;
        $this->footerText = $footerText;
    }

    public function Header()
    {
        $this->Image($this->logo, 20, 13, 15, '', '', '', 'T', false, 300, '', false, false, 0, false, false, false);
        if (trim($this->lab) != '') {
            $this->SetFont('helvetica', 'B', 20);
            $this->writeHTMLCell(0, 0, 5, 18, strtoupper($this->lab), 0, 0, 0, true, 'C', true);
        }
        $this->writeHTMLCell(0, 0, 10, 36, '<hr>', 0, 0, 0, true, 'C', true);
    }

    public function Footer()
    {
        $this->SetY(-15);
        $this->SetFont('helvetica', '', 7);
        $this->Cell(0, 10, '.... ' . $this->footerText . ' ....', 0, false, 'C', 0, '', 0, false, 'T', 'M');
    }
}
