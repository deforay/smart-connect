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
}
