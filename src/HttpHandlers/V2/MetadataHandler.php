<?php

declare(strict_types=1);

namespace App\HttpHandlers\V2;

/** POST /api/v2/metadata — replaces POST /api/vlsm-metadata. */
final class MetadataHandler extends AbstractIngestHandler
{
    protected function fileField(): string
    {
        return 'referenceFile';
    }

    protected function tempFolder(): string
    {
        return 'vlsm-reference';
    }

    protected function ingest(): mixed
    {
        return $this->bridge->get('CommonService')->saveVlsmMetadataFromAPI($_POST);
    }
}
