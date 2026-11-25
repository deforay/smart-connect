<?php

namespace Application\Pdf;

use setasign\Fpdi\Tcpdf\Fpdi;

/**
 * Joins PDF files into one document, page by page.
 */
class PdfConcat extends Fpdi
{
    /** @param string[] $files */
    public function concat(array $files): void
    {
        foreach ($files as $file) {
            $pageCount = $this->setSourceFile($file);
            for ($i = 1; $i <= $pageCount; $i++) {
                $template = $this->importPage($i);
                $size = $this->getTemplateSize($template);
                $this->AddPage('P', [$size['width'], $size['height']]);
                $this->useTemplate($template);
            }
        }
    }
}
