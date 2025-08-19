<?php
/*
    Developed by Yesbabylon – https://yesbabylon.com
    (c) 2025–2026 Yesbabylon SA
    Licensed under the GNU AGPL v3 License – https://www.gnu.org/licenses/agpl-3.0.html
*/
namespace realestate\property;

class Apportionment extends \equal\orm\Model {

    public static function getColumns() {

        return [
            'condo_id' => [
                'type'              => 'many2one',
                'description'       => "The condominium the property lot belongs to.",
                'foreign_object'    => 'realestate\property\Condominium',
                'required'          => true,
                'dependents'        => ['code']
            ],

            'name' => [
                'type'              => 'computed',
                'result_type'       => 'string',
                'function'          => 'calcName',
                'description'       => "Name of the apportionment.",
                'store'             => true,
                'readonly'          => true
            ],

            'description' => [
                'type'              => 'string',
                'description'       => "Short description of the apportionment.",
                'required'          => true,
                'dependents'        => ['name']
            ],

            'code' => [
                'type'              => 'computed',
                'result_type'       => 'string',
                'function'          => 'calcApportionmentCode',
                'store'             => true,
                'description'       => "Code for the apportionment.",
                'help'              => "Code is arbitrary and is used to match apportionment with accounting accounts.",
                'dependents'        => ['name']
            ],

            'is_statutory' => [
                'type'              => 'boolean',
                'description'       => "The apportionment holds the statutory quotas.",
                'help'              => "Apportionment describes the rights on the condominium's common areas as defined in the notary deed.",
                'default'           => false,
                'dependents'        => ['name', 'code']
            ],

            'common_area_id' => [
                'type'              => 'many2one',
                'description'       => "The type of the common area.",
                'foreign_object'    => 'realestate\property\CommonAreaType',
                'visible'           => ['is_statutory', '=', false]
            ],

            'apportionment_shares_ids' => [
                'type'              => 'one2many',
                'foreign_object'    => 'realestate\property\PropertyLotApportionmentShare',
                'foreign_field'     => 'apportionment_id',
                'description'       => "The shares referring to the apportionment.",
                'domain'            => [['condo_id', '=', 'object.condo_id'], ['apportionment_id', '=', 'object.id']]
            ],

            /*
            // #memo - this creates a unique constraint on [property_lot_id, apportionment_id], while condo_id is also part of the key
            'property_lots_ids' => [
                'type'              => 'many2many',
                'foreign_object'    => 'realestate\property\PropertyLot',
                'foreign_field'     => 'apportionments_ids',
                'rel_table'         => 'realestate_property_propertylotapportionmentshare',
                'rel_foreign_key'   => 'property_lot_id',
                'rel_local_key'     => 'apportionment_id',
                'description'       => 'Property lots that are assigned to this key.'
            ],
            */

            'count_property_lots' => [
                'type'              => 'computed',
                'result_type'       => 'integer',
                'function'          => 'calcCountPropertyLots',
                'description'       => 'Total amount of property lots assigned to this nature.',
                'store'             => true
            ],

            'total_shares' => [
                'type'              => 'integer',
                'description'       => "The total number of shares considered for this apportionment.",
                'default'           => 1000,
                'dependents'        => ['name']
            ],

            'assigned_shares' => [
                'type'              => 'computed',
                'result_type'       => 'integer',
                'function'          => 'calcAssignedShares',
                'description'       => "The total number of assigned shares (for control).",
                'store'             => false
            ],

            'is_active' => [
                'type'              => 'boolean',
                'description'       => "Flag marking the apportionment as active.",
                'help'              => "Apportionments cannot be removed, but marking them as non-active hide them in the selection lists.",
                'default'           => true
            ],

            'status' => [
                'type'              => 'string',
                'selection'         => [
                    'pending',
                    'validated'
                ],
                'default'           => 'pending',
                'description'       => 'Status of the Apportionment.'
            ]

        ];
    }

    public static function getWorkflow() {
        return [
            'pending' => [
                'description' => 'Draft apportionment, still waiting to be completed for validation.',
                'icon' => 'draw',
                'transitions' => [
                    'validate' => [
                        'description' => 'Publish the Apportionment (this cannot be undone).',
                        'policies'    => ['can_validate'],
                        'onbefore'    => 'onbeforeValidate',
                        'status'      => 'validated',
                    ]
                ]
            ]
        ];
    }

    public static function getPolicies(): array {
        return [
            'can_validate' => [
                'description' => 'Verifies that the Apportionment can be validated.',
                'function'    => 'policyCanValidate'
            ]
        ];
    }

    public static function getActions() {
        return [
            'duplicate' => [
                'description'   => 'Creates a new invoice of type credit note to reverse invoice.',
                'help'          => 'Reversing an invoice can only be done when status is "invoice".',
                'policies'      => [],
                'function'      => 'doDuplicateApportionment'
            ]
        ];
    }

    protected static function policyCanValidate($self) {
        $result = [];

        $self->read(['status', 'assigned_shares', 'total_shares']);

        foreach($self as $id => $apportionment) {
            if($apportionment['status'] === 'validated') {
                $result[$id] = [
                    'not_allowed' => 'Apportionment is already validated and active.'
                ];
            }
            if($apportionment['assigned_shares'] !== $apportionment['total_shares']) {
                $result[$id] = [
                    'not_balanced' => 'Assigned shares does not match apportionment total.'
                ];
            }
        }

        return $result;
    }

    public static function canupdate($self, $values) {
        $self->read(['status']);
        foreach($self as $id => $apportionment) {
            if($apportionment['status'] == 'validated') {
                return ['status' => ['not_allowed' => 'Validated apportionment cannot be modified.']];
            }
        }
        return parent::canupdate($self);
    }

    public static function onbeforeValidate($self) {
        $self->update(['code' => null, 'name' => null]);
    }

    public static function calcName($self) {
        $result = [];
        $self->read(['state', 'status', 'is_statutory', 'total_shares', 'code', 'description']);
        foreach($self as $id => $apportionment) {
            if($apportionment['state'] != 'instance') {
                continue;
            }
            if(!$apportionment['code']) {
                continue;
            }
            $name = ($apportionment['is_statutory']) ? '' : $apportionment['code'] . ' - ';
            $result[$id] = $name . $apportionment['description'] .' (Q. '.$apportionment['total_shares'].')';
        }
        return $result;
    }

    public static function calcApportionmentCode($self) {
        $result = [];
        $self->read(['state', 'status', 'is_statutory', 'condo_id']);
        foreach($self as $id => $apportionment) {
            if($apportionment['state'] != 'instance') {
                continue;
            }
            if($apportionment['is_statutory']) {
                $result[$id] = 'STAT';
            }
            else {
                $count = count(self::search([['is_statutory', '=', false], ['condo_id', '=', $apportionment['condo_id']]])->ids());
                $result[$id] = sprintf("%04d", $count);
            }
        }
        return $result;
    }

    public static function calcAssignedShares($self) {
        $result = [];
        $self->read(['apportionment_shares_ids' => ['property_lot_shares']]);
        foreach($self as $id => $apportionment) {
            $result[$id] = 0;
            foreach($apportionment['apportionment_shares_ids'] as $share) {
                $result[$id] += $share['property_lot_shares'];
            }
        }
        return $result;

    }

    public static function calcCountPropertyLots($self) {
        $result = [];
        $self->read(['apportionment_shares_ids']);
        foreach($self as $id => $key) {
            $result[$id] = count($key['apportionment_shares_ids'] ?? []);
        }
        return $result;
    }

    public static function doDuplicateApportionment($self) {
        $self->read(['condo_id', 'description', 'is_statutory', 'common_area_id', 'total_shares', 'apportionment_shares_ids']);

        foreach($self as $id => $apportionment) {
            // 1) create a new Apportionment with (copy) as prefix
            $new_apportionment = self::create([
                    'condo_id'         => $apportionment['condo_id'],
                    'description'      => '(copy) ' . $apportionment['description'],
                    'is_statutory'     => $apportionment['is_statutory'],
                    'common_area_id'   => $apportionment['common_area_id'],
                    'total_shares'     => $apportionment['total_shares']
                ])
                ->first();

            // 2) copy lines
            $shares = PropertyLotApportionmentShare::ids($apportionment['apportionment_shares_ids'])->read(['property_lot_id', 'property_lot_shares']);
            foreach($shares as $share) {
                PropertyLotApportionmentShare::create([
                        'condo_id'              => $apportionment['condo_id'],
                        'apportionment_id'      => $new_apportionment['id'],
                        'property_lot_id'       => $share['property_lot_id'],
                        'property_lot_shares'   => $share['property_lot_shares']
                    ]);
            }
        }

    }
}