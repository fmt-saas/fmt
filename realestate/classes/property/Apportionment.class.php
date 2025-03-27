<?php
/*
    This file is part of FMT SaaS Software <https://github.com/fmt-saas/fmt>
    Some Rights Reserved, FMT SRL, 2025-2026
    Original author(s): Yesbabylon SA
    Licensed under GNU AGPL 3 license <http://www.gnu.org/licenses/>
*/
namespace realestate\property;

class Apportionment extends \equal\orm\Model {

    public static function getColumns() {

        return [
            'condo_id' => [
                'type'              => 'many2one',
                'description'       => "The condominium the property lot belongs to.",
                'foreign_object'    => 'realestate\property\Condominium',
                'required'          => true
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

            'apportionment_code' => [
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
                'dependents'        => ['name']
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
                'description'       => "The apportionment referring to the apportionment.",
                "domain"            => ['condo_id', '=', 'object.condo_id']
            ],

            'property_lots_ids' => [
                'type'              => 'many2many',
                'foreign_object'    => 'realestate\property\PropertyLot',
                'foreign_field'     => 'apportionments_ids',
                'rel_table'         => 'realestate_property_propertylotapportionmentshare',
                'rel_foreign_key'   => 'property_lot_id',
                'rel_local_key'     => 'apportionment_id',
                'description'       => 'Property lots that are assigned to this key.'
            ],

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

    public static function calcName($self) {
        $result = [];
        $self->read(['is_statutory', 'total_shares', 'apportionment_code', 'description']);
        foreach($self as $id => $apportionment) {
            $name = ($apportionment['is_statutory']) ? '' : $apportionment['apportionment_code'] . ' - ';
            $result[$id] = $name . $apportionment['description'] .' (Q. '.$apportionment['total_shares'].')';
        }
        return $result;
    }

    public static function calcApportionmentCode($self) {
        $result = [];
        $self->read(['state', 'is_statutory', 'condo_id']);
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
        $self->read(['property_lots_ids']);
        foreach($self as $id => $key) {
            $result[$id] = count($key['property_lots_ids'] ?? []);
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