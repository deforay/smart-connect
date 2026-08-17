<?php

declare(strict_types=1);

namespace App\HttpHandlers\V2;

/** POST /api/v2/vl — replaces POST /api/vlsm. */
final class ReceiveVlHandler extends AbstractIngestHandler
{
    protected function fileField(): string
    {
        return 'vlFile';
    }

    protected function tempFolder(): string
    {
        return 'vlsm-vl';
    }

    protected function ingest(): mixed
    {
        return $this->bridge->get('SampleService')->saveFileFromVlsmAPIV2();
    }
}
