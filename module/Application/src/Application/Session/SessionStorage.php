<?php

declare(strict_types=1);

namespace Application\Session;

/**
 * Session storage, reduced to the clear() the logout paths call.
 */
class SessionStorage
{
    /**
     * Without a name this empties every namespace and ends the session, which
     * is what logout needs: Laminas' clear() on the storage wiped the whole
     * session, not just the container that reached it.
     *
     * With a name it drops that one namespace and leaves the session running,
     * again as Laminas did. UsersTable depends on the difference — it clears
     * 'credo' to block a role 7 login until the OTP is entered, then writes
     * the pending credentials into another namespace on the same session.
     */
    public function clear(?string $name = null): void
    {
        if ($name !== null) {
            unset($_SESSION[$name]);
            return;
        }

        $_SESSION = [];

        if (session_status() !== PHP_SESSION_ACTIVE) {
            return;
        }

        // Expire the cookie too, otherwise the browser keeps presenting a
        // session id that is now empty but still valid.
        if (ini_get('session.use_cookies') && !headers_sent()) {
            $params = session_get_cookie_params();
            setcookie(
                session_name(),
                '',
                [
                    'expires' => time() - 42000,
                    'path' => $params['path'],
                    'domain' => $params['domain'],
                    'secure' => $params['secure'],
                    'httponly' => $params['httponly'],
                    'samesite' => $params['samesite'] ?? 'Lax',
                ]
            );
        }

        session_destroy();
    }
}
