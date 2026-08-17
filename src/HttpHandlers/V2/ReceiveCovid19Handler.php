<?php

declare(strict_types=1);

namespace App\HttpHandlers\V2;

/** POST /api/v2/covid19 — replaces POST /api/vlsm-covid19. */
final class ReceiveCovid19Handler extends AbstractIngestHandler
{
    protected function fileField(): string
    {
        return 'covid19File';
    }

    protected function tempFolder(): string
    {
        return 'vlsm-covid19';
    }

    protected function ingest(): mixed
    {
        return $this->bridge->get('Covid19FormService')->saveFileFromVlsmAPIV2();
    }
}
