<?php

declare(strict_types=1);

namespace Application\Controller\Traits;

use Application\Model\Acl;

trait AclAwareTrait
{
    /** @var Acl $acl */
    protected $acl;

    public function setAcl(Acl $acl): void
    {
        $this->acl = $acl;
    }

    public function getAcl(): Acl
    {
        return $this->acl;
    }

    public function isAllowed($role = null, $resource = null, $privilege = null): bool
    {
        return $this->acl->isAllowed($role, $resource, $privilege);
    }

    /**
     * Whether the session role holds $privilege on this controller. The
     * controller's class name is its resource id in dash_privileges.
     *
     * Module.php skips its ACL check on XmlHttpRequest, so an action that
     * saves or lists admin data checks here as well.
     */
    protected function hasPrivilege(string $privilege): bool
    {
        $role = (new \Application\Session\Container('credo'))->roleCode;
        return $this->acl->isAllowed($role, static::class, $privilege);
    }

    protected function forbidden()
    {
        /** @var \Laminas\Http\Response $response */
        $response = $this->getResponse();
        $response->setStatusCode(403);
        $response->setContent('Forbidden');
        return $response;
    }
}
