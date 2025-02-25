<?php
/*
    This file is part of the eQual framework <http://www.github.com/equalframework/equal>
    Some Rights Reserved, eQual framework, 2010-2024
    Original author(s): Cédric FRANCOYS
    Licensed under GNU LGPL 3 license <http://www.gnu.org/licenses/>
*/
namespace fmt\access;

use equal\orm\ObjectManager;
use fmt\setting\Setting;
use hr\Permission;
use hr\role\Role;
use hr\role\RoleAssignment;

class AccessController extends \equal\access\AccessController {

    private $cache_roles_map = [];
    private $cache_rights_map = [];


    /**
     * Cache rights for a given user, operation and class.
     * Create the cache structure if it does not exist yet.
     *
     */
    private function cacheRights($user_id, $operation, $object_class, $rights) {
        if(!isset($this->cache_rights_map[$user_id])) {
            $this->cache_rights_map[$user_id] = [];
        }

        if(!isset($this->cache_rights_map[$user_id][$operation])) {
            $this->cache_rights_map[$user_id][$operation] = [];
        }

        $this->cache_rights_map[$user_id][$operation][$object_class] = $rights;
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

        if(!isset($this->cache_roles_map[$user_id][$condo_id])) {

            /** @var \equal\orm\ObjectManager */
            $orm = $this->container->get('orm');

            // convert role to role_id
            $ids = $orm->search(Role::getType(), ['code', '=', $role]);

            if(!is_array($ids)) {
                trigger_error("APP::userHasRole(): unknown role '$role'", EQ_REPORT_WARNING);
                return false;
            }

            $role_id = current($ids);

            // check if user has an assignment for this role
            // #memo - if $condo_id is null, it will fetch global entry, if any (i.e. the user has the role for any condo)
            $assignments_ids = $orm->search(RoleAssignment::getType(), [
                    ['user_id', '=', $user_id],
                    ['role_id', '=', $role_id],
                    ['condo_id', '=', $condo_id]
                ]);

            $result = !empty($assignments_ids);

            if( !isset($this->cache_roles_map[$user_id]) ) {
                $this->cache_roles_map[$user_id] = [];
            }

            $this->cache_roles_map[$user_id][$condo_id] = $result;
        }

        return $this->cache_roles_map[$user_id][$condo_id];
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

        if($object_class === '*') {
            return parent::userIsAllowed($user_id, $operation, $object_class, $object_fields, $object_ids);
        }
        elseif(isset($this->cache_rights_map[$user_id][$operation][$object_class])) {
            $rights = $this->cache_rights_map[$user_id][$operation][$object_class];
        }
        else {
            $rights = 0;

            while( !isset($this->cache_rights_map[$user_id][$operation][$object_class]) ) {

                // retrieve rights from user and its groups
                $rights |= parent::getUserRights($user_id, $object_class, $object_ids, $operation);
                var_dump($rights);

                if( ($rights & $operation) === $operation ) {
                    break;
                }

                $model = $orm->getModel($object_class);
                if($model === false) {
                    trigger_error("APP::isAllowed(): unknown class '$object_class'", EQ_REPORT_WARNING);
                    return false;
                }

                $schema = $model->getSchema();
                // check user roles only for classes relating to condominiums
                if(!isset($schema['condo_id'])) {
                    break;
                }
                else {
                    echo 'checking roles';
                    $condo_id = Setting::get('fmt', 'main', 'user.condo_id', null, ['user_id' => $user_id]);
                    // check if user has an assignment for this role
                    // #memo - if $condo_id is null, it will fetch global entries, if any (i.e. the user has the role for any condo)
                    $assignments_ids = $orm->search(RoleAssignment::getType(), [
                            ['user_id', '=', $user_id],
                            ['condo_id', '=', $condo_id]
                        ]);

                    if(!is_array($assignments_ids)) {
                        return false;
                    }

                    $assignments = $orm->read(RoleAssignment::getType(), $assignments_ids, ['role_id']);
                    $roles_ids = array_map(function($a) { return $a['role_id']; }, $assignments);
                    $permissions_ids = $orm->search(Permission::getType(), ['role_id', 'in', $roles_ids]);

                    if(!is_array($permissions_ids)) {
                        return false;
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

                    break;
                }
            }

            $this->cacheRights($user_id, $operation, $object_class, $rights);
        }

        return ($rights & $operation) === $operation;
    }

}
