<?php

namespace Application\View\Helper;

use App\Version;
use Laminas\View\Helper\AbstractHelper;

/**
 * The ref the running code was deployed from, for the post-login footer.
 *
 * Country teams report problems by version, but a version covers many commits.
 * The ref identifies the exact code an instance runs without shell access to
 * the server.
 */
class DeployedRef extends AbstractHelper
{
    public function __invoke(): ?string
    {
        return Version::ref();
    }
}
