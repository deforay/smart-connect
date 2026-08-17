<?php

declare(strict_types=1);

namespace Application\I18n;

use Symfony\Component\Translation\Loader\MoFileLoader;
use Symfony\Component\Translation\Loader\PoFileLoader;
use Symfony\Component\Translation\Translator as SymfonyTranslator;

/**
 * Application translator, backed by symfony/translation.
 *
 * This replaces laminas-i18n's translator and keeps its method names, so the
 * call sites do not change: the views reach it through the translate() view
 * helper, Module::initTranslator() still chains setLocale()->setFallbackLocale(),
 * and the services that take it injected still call translate().
 *
 * The catalogue format is unchanged — the same gettext .mo files under
 * module/Application/language, read by Symfony's MoFileLoader. Nothing needs
 * regenerating, and a message with no translation returns the source string,
 * as it did before.
 */
class Translator
{
    private SymfonyTranslator $translator;

    public function __construct(string $locale = 'en_US')
    {
        $this->translator = new SymfonyTranslator($locale);
        $this->translator->addLoader('mo', new MoFileLoader());
        $this->translator->addLoader('po', new PoFileLoader());
    }

    /**
     * Register a directory of catalogues the way laminas-i18n's
     * translation_file_patterns did: a base directory plus a filename pattern
     * in which %s stands for the locale, e.g. '%s.mo'. The locale is read back
     * out of each filename the pattern matches.
     */
    public function addPattern(string $baseDir, string $pattern): void
    {
        $baseDir = rtrim($baseDir, DIRECTORY_SEPARATOR);
        $regex = '/^' . str_replace('%s', '(.+)', preg_quote($pattern, '/')) . '$/';

        foreach (glob($baseDir . DIRECTORY_SEPARATOR . sprintf($pattern, '*')) ?: [] as $file) {
            if (!preg_match($regex, basename($file), $matches)) {
                continue;
            }

            $this->translator->addResource(
                pathinfo($file, PATHINFO_EXTENSION),
                $file,
                $matches[1]
            );
        }
    }

    public function translate(string $message, ?string $textDomain = null, ?string $locale = null): string
    {
        if ($message === '') {
            return '';
        }

        return $this->translator->trans($message, [], $textDomain ?: 'messages', $locale);
    }

    /**
     * Empty locales are ignored rather than passed through: the language comes
     * from the session, which is unset until a language is chosen, and Symfony
     * rejects an empty locale where Laminas quietly kept the current one.
     */
    public function setLocale(?string $locale): static
    {
        if (!empty($locale)) {
            $this->translator->setLocale($locale);
        }

        return $this;
    }

    public function setFallbackLocale(?string $locale): static
    {
        if (!empty($locale)) {
            $this->translator->setFallbackLocales([$locale]);
        }

        return $this;
    }

    public function getLocale(): string
    {
        return $this->translator->getLocale();
    }
}
