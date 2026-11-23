<?php

namespace Application\Service;

use Application\Controller;
use Application\Model\Acl;

/**
 * The page a signed-in user is sent to: after login, from /login when a
 * session already exists, and from the home page.
 *
 * Each candidate is a route and the controller action it reaches, and a user
 * is sent to the first one the ACL lets their role open. This used to be a
 * role-number switch that named a route without an action. 'laboratory' and
 * 'clinics' default to indexAction, which lab and clinic users have no
 * privilege for, so both roles landed on a 403 straight after logging in, and
 * every role without a Summary privilege did the same.
 *
 * The role id only orders the candidates, keeping lab users on the Labs
 * dashboard and clinic users on the Clinics dashboard as before. It never
 * grants anything.
 */
final class LandingPage
{
    /** route name => [controller, action] */
    private const PAGES = [
        'summary'    => [Controller\SummaryController::class, 'dashboard'],
        'laboratory' => [Controller\LaboratoryController::class, 'dashboard'],
        'clinics'    => [Controller\ClinicController::class, 'dashboard'],
        'snapshot'   => [Controller\SnapshotController::class, 'index'],
    ];

    /** role id => the route tried first */
    private const PREFERRED = [
        2 => 'laboratory',
        3 => 'clinics',
    ];

    /**
     * @param string[] $prefer routes to try before the usual order
     * @return array{route: string, params: array<string, string>}|null
     *         null when the role may open none of the pages
     */
    public static function for(Acl $acl, $roleCode, $roleId, array $prefer = []): ?array
    {
        $order = $prefer;
        if (isset(self::PREFERRED[(int) $roleId])) {
            $order[] = self::PREFERRED[(int) $roleId];
        }
        $order = array_unique(array_merge($order, array_keys(self::PAGES)));

        foreach ($order as $route) {
            if (!isset(self::PAGES[$route])) {
                continue;
            }
            [$controller, $action] = self::PAGES[$route];
            if ($acl->isAllowed($roleCode, $controller, $action)) {
                return ['route' => $route, 'params' => ['action' => $action]];
            }
        }

        return null;
    }
}
