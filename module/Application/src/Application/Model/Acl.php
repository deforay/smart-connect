<?php

namespace Application\Model;

/**
 * Role-based access control for the dashboard.
 *
 * This used to extend laminas-permissions-acl, of which the application used
 * five methods and none of the features — no role inheritance, no deny rules,
 * no assertions, no resource tree. What is left once those go is a flat
 * allow-list built from the resources, roles, privileges and
 * roles_privileges_map tables, which is what this class holds directly.
 *
 * Externally only isAllowed() and hasResource() are called, so the swap is
 * invisible to the 39 call sites in the controllers, models and layout.
 *
 * Two deliberate differences from the Laminas behaviour, both denying where
 * it threw: an unknown role or an unknown resource returns false rather than
 * raising InvalidArgumentException. A resource that vanishes from the
 * database therefore hides a menu entry instead of producing a 500, and no
 * case grants access that Laminas would have refused.
 */
class Acl
{
    /** @var array<string, true> */
    private array $roles = [];

    /** @var array<string, true> */
    private array $resources = [];

    /** @var array<string, array<string, array<string, true>>> role => resource => privilege */
    private array $allowed = [];

    /** @var array<string, true> roles holding a blanket allow */
    private array $allowedEverything = [];

    public function __construct($resourceList, $rolesList, $rolePrivileges, $privileges)
    {
        foreach ($resourceList as $res) {
            $this->addResource($res['resource_id']);
        }

        foreach ($rolesList as $rol) {
            $this->addRole($rol['role_code']);
        }

        // Map privileges to resource and privilege names
        $privilegeMap = [];
        foreach ($privileges as $priv) {
            $privilegeMap[$priv['privilege_id']] = [
                'resource_id' => $priv['resource_id'],
                'privilege_name' => $priv['privilege_name']
            ];
        }

        $result = [];

        // Initialize all roles and resources
        foreach ($rolesList as $role) {
            $roleCode = $role['role_code'];
            $result[$roleCode] = [];

            foreach ($resourceList as $res) {
                $resourceId = $res['resource_id'];
                $result[$roleCode][$resourceId] = [];
            }
        }

        // Update privileges to 'allow' based on role-privilege mappings
        foreach ($rolePrivileges as $rp) {
            $roleId = $rp['role_id'];
            $privilegeId = $rp['privilege_id'];

            // Find the role code by role_id
            $roleCode = null;
            foreach ($rolesList as $role) {
                if ($role['role_id'] == $roleId) {
                    $roleCode = $role['role_code'];
                    break;
                }
            }

            // If role code and privilege mapping is found, update the result
            if ($roleCode && isset($privilegeMap[$privilegeId])) {
                $resourceId = $privilegeMap[$privilegeId]['resource_id'];
                $privilegeName = $privilegeMap[$privilegeId]['privilege_name'];

                // Ensure the resource is in the result array
                if (!isset($result[$roleCode][$resourceId])) {
                    $result[$roleCode][$resourceId] = [];
                }

                // Set the privilege to 'allow'
                $result[$roleCode][$resourceId][$privilegeName] = 'allow';
            }
        }

        $config = $result;

        foreach ($config as $role => $resources) {
            $this->addRole($role);
            foreach ($resources as $resource => $permissions) {
                foreach ($permissions as $privilege => $permission) {
                    $this->$permission($role, $resource, $privilege);
                }
            }
        }

        $this->addRole('daemon');
        $this->allow('daemon');
    }

    public function hasResource($resource): bool
    {
        return isset($this->resources[(string) $resource]);
    }

    public function hasRole($role): bool
    {
        return isset($this->roles[(string) $role]);
    }

    /**
     * A role with a blanket allow still needs the resource to be registered,
     * matching Laminas, where isAllowed() resolved the resource before it
     * consulted any rule.
     */
    public function isAllowed($role = null, $resource = null, $privilege = null): bool
    {
        $role = (string) $role;

        if (!isset($this->roles[$role])) {
            return false;
        }

        if ($resource !== null && !isset($this->resources[(string) $resource])) {
            return false;
        }

        if (isset($this->allowedEverything[$role])) {
            return true;
        }

        if ($resource === null || $privilege === null) {
            return false;
        }

        return isset($this->allowed[$role][(string) $resource][(string) $privilege]);
    }

    private function addResource($resource): void
    {
        $this->resources[(string) $resource] = true;
    }

    private function addRole($role): void
    {
        $this->roles[(string) $role] = true;
    }

    /**
     * Called with a role alone this is a blanket allow, as it was in Laminas,
     * where a null resource and privilege meant "all of them".
     */
    private function allow($role, $resource = null, $privilege = null): void
    {
        $role = (string) $role;
        $this->addRole($role);

        if ($resource === null || $privilege === null) {
            $this->allowedEverything[$role] = true;
            return;
        }

        $this->allowed[$role][(string) $resource][(string) $privilege] = true;
    }
}
