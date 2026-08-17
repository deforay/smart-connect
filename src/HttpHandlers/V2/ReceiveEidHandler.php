<?php

declare(strict_types=1);

namespace App\HttpHandlers\V2;

/** POST /api/v2/eid — replaces POST /api/vlsm-eid. */
final class ReceiveEidHandler extends AbstractIngestHandler
{
    protected function fileField(): string
    {
        return 'eidFile';
    }

    protected function tempFolder(): string
    {
        return 'vlsm-eid';
    }

    protected function ingest(): mixed
    {
        return $this->bridge->get('EidSampleService')->saveFileFromVlsmAPIV2();
    }
}
