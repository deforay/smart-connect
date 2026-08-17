<?php

declare(strict_types=1);

namespace Application\Session;

/**
 * The small part of Laminas\Session\SessionManager the application used:
 * getStorage()->clear() at logout. Kept as two objects rather than collapsed
 * into one so the call sites read as they did before.
 */
class SessionManager
{
    public function getStorage(): SessionStorage
    {
        return new SessionStorage();
    }

    public function destroy(): void
    {
        (new SessionStorage())->clear();
    }
}
