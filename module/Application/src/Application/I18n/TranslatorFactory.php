<?php

declare(strict_types=1);

namespace Application\I18n;

use Psr\Container\ContainerInterface;

/**
 * Builds the 'translator' service from the 'translator' config block, which is
 * what Laminas\Mvc\I18n\TranslatorFactory used to do.
 *
 * This is a named class rather than a closure in module.config.php on purpose:
 * the merged module config is var_export()ed when 'config_cache_enabled' is
 * turned on, and a closure cannot be exported. Referenced by name, the config
 * stays cacheable exactly as it was before.
 */
class TranslatorFactory
{
    public function __invoke(ContainerInterface $container): Translator
    {
        $config = $container->get('Config')['translator'] ?? [];

        $translator = new Translator($config['locale'] ?? 'en_US');

        foreach ($config['translation_file_patterns'] ?? [] as $filePattern) {
            $translator->addPattern($filePattern['base_dir'], $filePattern['pattern']);
        }

        return $translator;
    }
}
