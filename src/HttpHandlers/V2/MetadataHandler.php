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

    /**
     * Metadata carries reference tables rather than patient records, so there is
     * no per-row identity to bind. The parameter is accepted to satisfy the base
     * class and deliberately unused.
     */
    protected function ingest(?array $credential): mixed
    {
        return $this->bridge->get('CommonService')->saveVlsmMetadataFromAPI($_POST);
    }
}
