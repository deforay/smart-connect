<?php

declare(strict_types=1);

namespace Application\View\Helper;

use Application\I18n\Translator;
use Laminas\View\Helper\AbstractHelper;

/**
 * The translate() helper the views call, ~3300 times.
 *
 * laminas-i18n used to supply this. It is registered here now that the
 * translator behind it is Application\I18n\Translator, with the same
 * signature, so no view changes.
 */
class Translate extends AbstractHelper
{
    public function __construct(private readonly Translator $translator)
    {
    }

    public function __invoke(string $message, ?string $textDomain = null, ?string $locale = null): string
    {
        return $this->translator->translate($message, $textDomain, $locale);
    }
}
