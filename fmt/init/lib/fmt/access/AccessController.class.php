<?php
/*
    Developed by Yesbabylon – https://yesbabylon.com
    (c) 2025–2026 Yesbabylon SA
    Licensed under the GNU AGPL v3 License – https://www.gnu.org/licenses/agpl-3.0.html
*/
namespace fmt\access;

use equal\orm\ObjectManager;
use hr\Permission;
use hr\role\Role;
use hr\role\RoleAssignment;

class AccessController extends \equal\access\AccessController {

    private $cache_roles_map = [];

    private function cacheRole($user_id, $condo_id, $role, $has_role) {
        if( !isset($this->cache_roles_map[$user_id]) ) {
            $this->cache_roles_map[$user_id] = [];
        }

        if( !isset($this->cache_roles_map[$user_id][$condo_id]) ) {
            $this->cache_roles_map[$user_id][$condo_id] = [];
        }

        $this->cache_roles_map[$user_id][$condo_id][$role] = $has_role;
    }

    public function userHasCondoRole($user_id, $role, $condo_id=null) {
        return $this->hasCondoRole($role, $condo_id, $user_id);
    }

    /**
     * Check if a given user is granted a specific role.
     * The behavior of this method differs from AccessController::hasRole() in that it uses hr\role\RoleAssignment.
     * Used to directly check if the current user has a role, in order to determine, for example, if they can perform an action.
     *
     * #memo - This method uses ORM service to avoid recursive calls to AccessController.
     *
     * @param string        $role             The role for which assignment is being tested.
     * @param integer|null  $condo_id         The identifier of the condo for which the test is requested. If null, it will check for global role.
     * @param integer       $user_id          The identifier of the user for which the test is requested.
     */
    public function hasCondoRole($role, $condo_id=null, $user_id=null): bool {

        if(!$user_id) {
            /** @var \equal\auth\AuthenticationManager */
            $auth = $this->container->get('auth');
            // retrieve current user identifier
            $user_id = $auth->userId();
        }

        if($user_id == EQ_ROOT_USER_ID) {
            return true;
        }

        $roles = (array) $role;
        foreach($roles as $role) {
            if(!isset($this->cache_roles_map[$user_id][$condo_id][$role])) {
                /** @var \equal\orm\ObjectManager */
                $orm = $this->container->get('orm');

                // convert role to role_id
                $ids = $orm->search(Role::getType(), ['code', '=', $role]);

                if(!is_array($ids)) {
                    trigger_error("APP::userHasRole(): unknown role '$role'", EQ_REPORT_WARNING);
                    return false;
                }

                $role_id = current($ids);

                // check if user has an assignment for this role on the targeted condo_id
                // #memo - if $condo_id is null, it will fetch global entry, if any (i.e. the user has the role for any condo)
                $assignments_ids = $orm->search(RoleAssignment::getType(), [
                        ['user_id', '=', $user_id],
                        ['role_id', '=', $role_id],
                        ['condo_id', '=', $condo_id]
                    ]);

                if($condo_id && empty($assignments_ids)) {
                    // check if user has a global role assignment
                    $assignments_ids = $orm->search(RoleAssignment::getType(), [
                            ['user_id', '=', $user_id],
                            ['role_id', '=', $role_id],
                            ['condo_id', '=', null]
                        ]);
                }

                $has_role = !empty($assignments_ids);
                $this->cacheRole($user_id, $condo_id, $role, $has_role);
            }
            if($this->cache_roles_map[$user_id][$condo_id][$role]) {
                return true;
            }
        }

        return false;
    }


    public function userIsAllowed($user_id, $operation, $object_class='*', $object_fields=[], $object_ids=[]) {
        return $this->isAllowed($operation, $object_class, $object_fields, $object_ids, $user_id);
    }

    /**
     *  Check if current user (retrieved using Auth service) has rights to perform a given operation.
     *
     *  This method is called by the Collection service, when performing CRUD.
     *
     * @param integer       $operation        Identifier of the operation(s) that is/are checked (bit mask made of constants : EQ_R_CREATE, EQ_R_READ, EQ_R_UPDATE, EQ_R_DELETE, EQ_R_MANAGE).
     * @param string        $object_class     Class selector indicating on which classes the check must be performed.
     * @param string[]      $object_fields    (unused) Permissions granted by role rely only on object classes.
     * @param int[]         $object_ids       (unused) Permissions granted by role rely only on object classes.
     */
    public function isAllowed($operation, $object_class='*', $object_fields=[], $object_ids=[], $user_id=null) {
        /**
         * @var \equal\auth\AuthenticationManager  $auth
         * @var \equal\orm\ObjectManager           $orm
         */
        [$orm, $auth] = $this->container->get(['orm', 'auth']);

        // retrieve current user identifier
        if(is_null($user_id)) {
            $user_id = $auth->userId();
        }

        if(!is_array($object_ids)) {
            $object_ids = (array) $object_ids;
        }

        if($object_class === '*') {
            return parent::userIsAllowed($user_id, $operation, $object_class, $object_fields, $object_ids);
        }
        else {
            $rights = 0;

            while( true ) {

                // retrieve rights from user and its groups
                $rights |= parent::getUserRights($user_id, $object_class, $object_ids, $operation);

                if( ($rights & $operation) === $operation ) {
                    break;
                }

                $model = $orm->getModel($object_class);
                if($model === false) {
                    trigger_error("APP::isAllowed(): unknown class '$object_class'", EQ_REPORT_WARNING);
                    return false;
                }

                $schema = $model->getSchema();
                // check HR roles only for classes relating to condominiums
                if(isset($schema['condo_id'])) {

                    $domain = [];

                    if(count($object_ids)) {
                        $objects = $orm->read($model::getType(), $object_ids, ['condo_id']);
                        $condos_ids = array_map(function($o) { return $o['condo_id']; }, $objects);
                        $domain = [
                                // roles for condominiums specific to the objects (if $object_ids not empty)
                                [
                                    ['user_id', '=', $user_id],
                                    ['condo_id', 'in', $condos_ids]
                                ],
                                // roles for any condominium
                                [
                                    ['user_id', '=', $user_id],
                                    ['condo_id', 'is', null]
                                ]
                            ];
                    }
                    else {
                        // check for roles on whole class (not specific objects)
                        $domain = [
                                [
                                    ['user_id', '=', $user_id],
                                    ['condo_id', 'is', null]
                                ]
                            ];
                    }

                    // retrieve roles assignments for any of the related condominiums
                    $assignments_ids = $orm->search(RoleAssignment::getType(), $domain);

                    if(!is_array($assignments_ids) || !count($assignments_ids)) {
                        break;
                    }

                    // retrieve common roles (assigned to all objects), if any
                    $assignments = $orm->read(RoleAssignment::getType(), $assignments_ids, ['condo_id', 'role_id']);
                    $map_roles_by_condo = [];
                    foreach($assignments as $a) {
                        $map_roles_by_condo[$a['condo_id']][] = $a['role_id'];
                    }
                    if(count($map_roles_by_condo) <= 1) {
                        $roles_ids = (current($map_roles_by_condo)) ?: [];
                    }
                    else {
                        $roles_ids = array_intersect(...array_values($map_roles_by_condo));
                    }

                    // retrieve all permissions from granted roles
                    $permissions_ids = $orm->search(Permission::getType(), ['role_id', 'in', $roles_ids]);

                    if(!is_array($permissions_ids) || !count($permissions_ids)) {
                        break;
                    }

                    $permissions = $orm->read(Permission::getType(), $permissions_ids, ['class_name', 'rights']);

                    // check matches for the target class and all its parents
                    $classes = [
                        $object_class => true
                    ];

                    $parent_classes = ObjectManager::getObjectParentsClasses($object_class);
                    if(count($parent_classes)) {
                        $classes = [];
                        $table_name = $orm->getObjectTableName($object_class);
                        foreach($parent_classes as $class) {
                            if($orm->getObjectTableName($class) == $table_name) {
                                $classes[$class] = true;
                            }
                        }
                    }

                    foreach($permissions as $permission) {
                        foreach(array_keys($classes) as $class) {
                            if($class === $permission['class_name']) {
                                $rights |= $permission['rights'];
                                if( ($rights & $operation) === $operation ) {
                                    break;
                                }
                            }
                        }
                    }
                }
                break;
            }

        }

        return ($rights & $operation) === $operation;
    }

}
