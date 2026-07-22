<?php
/*
    Developed by Yesbabylon - https://yesbabylon.com
    (c) 2025-2026 Yesbabylon SA
    Licensed under the GNU AGPL v3 License - https://www.gnu.org/licenses/agpl-3.0.html
*/
namespace fmt\access;

use documents\Document;
use documents\navigation\Node;
use equal\access\AccessController;
use equal\orm\ObjectManager;
use identity\Identity;
use identity\User;
use purchase\supplier\Suppliership;
use realestate\ownership\Owner;

class DocumentAccessHelper {

    private $cache_user_scopes = [];

    private const MAP_CLASSES = [
        Document::class => [
            'visibility_field' => 'document_visibility'
        ],
        Node::class => [
            'visibility_field' => 'node_visibility'
        ]
    ];

    public function supports(string $object_class): bool {
        return isset(self::MAP_CLASSES[$object_class]);
    }

    public function userHasFullAccess(ObjectManager $orm, AccessController $access, int $user_id): bool {
        $scope = $this->getUserScope($orm, $access, $user_id);
        return $scope['has_full_access'];
    }

    public function getReadDomain(ObjectManager $orm, AccessController $access, string $object_class, int $user_id): array {
        if(!$this->supports($object_class)) {
            return [];
        }

        $scope = $this->getUserScope($orm, $access, $user_id);

        if($this->userHasFullAccess($orm, $access, $user_id)) {
            return [];
        }

        $visibility_field = self::MAP_CLASSES[$object_class]['visibility_field'];
        $domain = [];

        if(count($scope['condo_ids'])) {
            $domain[] = [
                [$visibility_field, '=', 'condo'],
                ['condo_id', 'in', $scope['condo_ids']]
            ];
        }

        if(count($scope['ownership_ids'])) {
            $domain[] = [
                [$visibility_field, '=', 'ownership'],
                ['ownership_id', 'in', $scope['ownership_ids']]
            ];
        }

        if(count($scope['owner_ids'])) {
            $domain[] = [
                [$visibility_field, '=', 'owner'],
                ['owner_id', 'in', $scope['owner_ids']]
            ];
        }

        if(count($scope['suppliership_ids'])) {
            $domain[] = [
                [$visibility_field, '=', 'suppliership'],
                ['suppliership_id', 'in', $scope['suppliership_ids']]
            ];
        }

        if(count($scope['supplier_ids'])) {
            $domain[] = [
                [$visibility_field, '=', 'suppliership'],
                ['supplier_id', 'in', $scope['supplier_ids']]
            ];
        }

        return count($domain) ? $domain : [['id', '=', 0]];
    }

    public function userCanReadObjects(ObjectManager $orm, AccessController $access, string $object_class, array $object_ids, int $user_id): bool {
        if(!$this->supports($object_class) || !count($object_ids)) {
            return true;
        }

        $scope = $this->getUserScope($orm, $access, $user_id);

        if($this->userHasFullAccess($orm, $access, $user_id)) {
            return true;
        }

        $fields = [
            self::MAP_CLASSES[$object_class]['visibility_field'],
            'condo_id',
            'ownership_id',
            'owner_id',
            'supplier_id',
            'suppliership_id'
        ];

        $objects = $orm->read($object_class, $object_ids, $fields);

        if(!is_array($objects) || count($objects) !== count($object_ids)) {
            return false;
        }

        foreach($objects as $object) {
            $object = $this->normalizeObject($object);
            if(!is_array($object) || !$this->canReadObject($object_class, $object, $scope)) {
                return false;
            }
        }

        return true;
    }

    private function getUserScope(ObjectManager $orm, AccessController $access, int $user_id): array {
        if(isset($this->cache_user_scopes[$user_id])) {
            return $this->cache_user_scopes[$user_id];
        }

        $scope = [
            'has_full_access'  => false,
            'identity_id'      => null,
            'owner_ids'        => [],
            'condo_ids'        => [],
            'ownership_ids'    => [],
            'supplier_ids'     => [],
            'suppliership_ids' => []
        ];

        if($user_id === EQ_ROOT_USER_ID) {
            $scope['has_full_access'] = true;
            return $this->cache_user_scopes[$user_id] = $scope;
        }

        if($access->hasGroup('admins', $user_id)) {
            $scope['has_full_access'] = true;
            return $this->cache_user_scopes[$user_id] = $scope;
        }

        $users = $orm->read(User::getType(), [$user_id], ['identity_id', 'employee_id']);
        $user = $this->normalizeObject(is_array($users) ? current($users) : null);

        if(!$user) {
            return $this->cache_user_scopes[$user_id] = $scope;
        }

        if(!empty($user['employee_id'])) {
            $scope['has_full_access'] = true;
            return $this->cache_user_scopes[$user_id] = $scope;
        }

        $scope['identity_id'] = $user['identity_id'] ?? null;

        if(!$scope['identity_id']) {
            return $this->cache_user_scopes[$user_id] = $scope;
        }

        $owners_ids = $orm->search(Owner::getType(), ['identity_id', '=', $scope['identity_id']]);

        if(is_array($owners_ids) && count($owners_ids)) {
            $scope['owner_ids'] = array_values($owners_ids);

            $owners = $orm->read(Owner::getType(), $owners_ids, ['condo_id', 'ownership_id']);
            foreach($owners as $owner) {
                $owner = $this->normalizeObject($owner);
                if(!is_array($owner)) {
                    continue;
                }
                if(isset($owner['condo_id'])) {
                    $scope['condo_ids'][$owner['condo_id']] = true;
                }
                if(isset($owner['ownership_id'])) {
                    $scope['ownership_ids'][$owner['ownership_id']] = true;
                }
            }

            $scope['condo_ids'] = array_keys($scope['condo_ids']);
            $scope['ownership_ids'] = array_keys($scope['ownership_ids']);
        }

        $identities = $orm->read(Identity::getType(), [$scope['identity_id']], ['supplier_id']);
        $identity = $this->normalizeObject(is_array($identities) ? current($identities) : null);

        if(isset($identity['supplier_id'])) {
            $scope['supplier_ids'] = [$identity['supplier_id']];

            $supplierships_ids = $orm->search(Suppliership::getType(), ['supplier_id', '=', $identity['supplier_id']]);
            if(is_array($supplierships_ids) && count($supplierships_ids)) {
                $scope['suppliership_ids'] = array_values($supplierships_ids);
            }
        }

        return $this->cache_user_scopes[$user_id] = $scope;
    }

    private function normalizeObject($object) {
        if(is_object($object) && method_exists($object, 'toArray')) {
            return $object->toArray();
        }

        return $object;
    }

    private function canReadObject(string $object_class, array $object, array $scope): bool {
        $visibility_field = self::MAP_CLASSES[$object_class]['visibility_field'];
        $visibility = $object[$visibility_field] ?? 'agency';

        switch($visibility) {
            case 'condo':
                return isset($object['condo_id']) && in_array($object['condo_id'], $scope['condo_ids']);

            case 'ownership':
                return isset($object['ownership_id']) && in_array($object['ownership_id'], $scope['ownership_ids']);

            case 'owner':
                return isset($object['owner_id']) && in_array($object['owner_id'], $scope['owner_ids']);

            case 'suppliership':
                return
                    (isset($object['suppliership_id']) && in_array($object['suppliership_id'], $scope['suppliership_ids']))
                    || (isset($object['supplier_id']) && in_array($object['supplier_id'], $scope['supplier_ids']));

            case 'agency':
            default:
                return false;
        }
    }
}
