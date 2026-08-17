<?php

declare(strict_types=1);

namespace Application\Session;

/**
 * Namespaced session container, replacing Laminas\Session\Container.
 *
 * laminas-session was used here as a namespaced wrapper over $_SESSION and
 * nothing more: five namespaces — credo, query, language, alert and
 * dataInterfaceLogin — reached entirely through property access, with no
 * session configuration, no validators, no expiration hops and no custom save
 * handler. This is that wrapper, over the PHP session directly.
 *
 * The session is started on first use, as Laminas did, so nothing has to
 * remember to start it. Values live under $_SESSION[$namespace], so a var_dump
 * of the session is now readable rather than a nest of ArrayObjects.
 */
class Container
{
    private string $namespace;

    public function __construct(string $namespace = 'Default')
    {
        if ($namespace === '') {
            throw new \InvalidArgumentException('Session namespace cannot be empty.');
        }

        $this->namespace = $namespace;

        // headers_sent() guards the CLI and the test harness, where starting a
        // session either is not wanted or would warn.
        if (session_status() === PHP_SESSION_NONE && !headers_sent()) {
            session_start();
        }

        if (!isset($_SESSION[$this->namespace]) || !is_array($_SESSION[$this->namespace])) {
            $_SESSION[$this->namespace] = [];
        }
    }

    public function __get(string $key): mixed
    {
        return $_SESSION[$this->namespace][$key] ?? null;
    }

    public function __set(string $key, mixed $value): void
    {
        $_SESSION[$this->namespace][$key] = $value;
    }

    /**
     * A null value reads as unset, which is what Laminas did and what the
     * `empty($session->userId)` checks throughout the app expect.
     */
    public function __isset(string $key): bool
    {
        return isset($_SESSION[$this->namespace][$key]);
    }

    public function __unset(string $key): void
    {
        unset($_SESSION[$this->namespace][$key]);
    }

    /**
     * Only so the two logout paths can keep reading as
     * $container->getManager()->getStorage()->clear(), which is the whole of
     * the manager API this application uses.
     */
    public function getManager(): SessionManager
    {
        return new SessionManager();
    }
}
