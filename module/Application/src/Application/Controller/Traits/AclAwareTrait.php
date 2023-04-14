<?php

declare(strict_types=1);

namespace Application\Controller\Traits;

use Laminas\Permissions\Acl\AclInterface;
use Laminas\Permissions\Acl\Resource\ResourceInterface;
use Laminas\Permissions\Acl\Role\RoleInterface;

trait AclAwareTrait
{
    /** @var AclInterface $acl */
    protected $acl;

    public function setAcl(AclInterface $acl): void
    {
        $this->acl = $acl;
    }

    public function getAcl(): AclInterface
    {
        return $this->acl;
    }

    /**
     * @param RoleInterface|string $role
     * @param ResourceInterface|string $resource
     * @param string $privilege
     */
    public function isAllowed($role = null, $resource = null, $privilege = null): bool
    {
        return $this->acl->isAllowed($role, $resource, $privilege);
    }
}
