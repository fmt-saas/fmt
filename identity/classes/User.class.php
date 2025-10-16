<?php
/*
    Developed by Yesbabylon – https://yesbabylon.com
    (c) 2025–2026 Yesbabylon SA
    Licensed under the GNU AGPL v3 License – https://www.gnu.org/licenses/agpl-3.0.html
*/
namespace identity;

class User extends \core\User {

    public static function getName() {
        return 'User';
    }

    public static function getColumns() {
        return [

            'name' => [
                'type'              => 'computed',
                'result_type'       => 'string',
                'description'       => "The name of the User.",
                'relation'          => ['identity_id' => 'name'],
                'store'             => true,
                'readonly'          => true
            ],

            'uuid' => [
                'type'              => 'string',
                'usage'             => 'text/plain:36',
                // #memo - commented for testing because items are on the same instance
                // #todo - uncomment for PROD
                // 'unique'            => true,
                'description'       => 'Unique identifier from the Master instance.'
            ],

            'identity_id' => [
                'type'              => 'many2one',
                'foreign_object'    => 'identity\Identity',
                'domain'            => ['type', '=', 'IN'],
                'description'       => 'The identity related to the user.',
                'help'              => 'The identity that this user relates to on the current instance. If set, the user details (name, email, etc.) will be synchronized from the identity.',  
                'onupdate'          => 'onupdateIdentityId',
                'dependencies'      => ['name']
            ],

            'setting_values_ids' => [
                'type'              => 'one2many',
                'foreign_object'    => 'core\setting\SettingValue',
                'foreign_field'     => 'user_id',
                'description'       => 'List of settings that relate to the user.'
            ],

            'owner_identity_id' => [
                'type'              => 'many2one',
                'foreign_object'    => 'identity\Identity',
                'description'       => "The organization the user relates to (defaults to current).",
                'default'           => 1
            ],

            'organisation_id' => [
                'type'              => 'many2one',
                'foreign_object'    => 'identity\Organisation',
                'description'       => 'The organisation the user belongs to.',
                'default'           => 1
            ],

            'role_assignments_ids' => [
                'type'              => 'one2many',
                'foreign_object'    => 'hr\role\RoleAssignment',
                'foreign_field'     => 'user_id',
                'description'       => 'Roles assigned to the user.'
            ]

        ];
    }

    public static function getActions() {
        return [
            'sync_from_identity' => [
                'description'   => 'Force sync values from related identity.',
                'function'      => 'doSyncFromIdentity'
            ]
        ];
    }

    public static function onupdateIdentityId($self) {
        $self->read(['identity_id']);
        foreach($self as $id => $user) {
            if($user['identity_id']) {
                Identity::id($user['identity_id'])->update(['user_id' => $id]);
            }
        }
    }

    protected static function doSyncFromIdentity($self, $orm) {
        static $common_fields = [
                'firstname',
                'lastname',
            ];

        $self->read(['identity_id']);
        foreach($self as $id => $identity) {
            if(!$identity['identity_id']) {
                continue;
            }

            $orm_events = $orm->disableEvents();

            $parentIdentity = Identity::id($identity['identity_id'])
                ->read($common_fields)
                ->first(true);

            if(!$parentIdentity) {
                continue;
            }

            $values = [];
            foreach($common_fields as $field) {
                if(array_key_exists($field, $parentIdentity)) {
                    $values[$field] = $parentIdentity[$field];
                }
            }
            self::id($id)->update($values);

            $orm->enableEvents($orm_events);
        }

    }

}