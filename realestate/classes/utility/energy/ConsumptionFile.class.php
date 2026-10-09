<?php
/*
    Developed by Yesbabylon - https://yesbabylon.com
    (c) 2025-2026 Yesbabylon SA
    Licensed under the GNU AGPL v3 License - https://www.gnu.org/licenses/agpl-3.0.html
*/
namespace realestate\utility\energy;

use realestate\property\PropertyLot;

class ConsumptionFile extends \equal\orm\Model {

    public static function getColumns() {
        return [
            'condo_id' => [
                'type'           => 'many2one',
                'description'    => 'The condominium the consumption file relates to.',
                'foreign_object' => 'realestate\property\Condominium',
                'required'       => true
            ],

            'suppliership_id' => [
                'type'           => 'many2one',
                'description'    => 'The suppliership the consumption file relates to.',
                'foreign_object' => 'purchase\supplier\Suppliership',
                'domain'         => ['condo_id', '=', 'object.condo_id'],
                'required'       => true,
                'dependents'     => ['name']
            ],

            'code' => [
                'type'        => 'string',
                'description' => 'Code identifying the consumption file.',
                'required'    => true,
                'dependents'  => ['name']
            ],

            'name' => [
                'type'        => 'computed',
                'result_type' => 'string',
                'description' => 'Display name composed of the supplier name and consumption file code.',
                'function'    => 'calcName',
                'store'       => true,
                'readonly'    => true
            ],

            'status' => [
                'type'        => 'string',
                'description' => 'Current configuration status of the consumption file.',
                'selection'   => ['pending', 'ready'],
                'default'     => 'pending'
            ],

            'consumption_file_sections_ids' => [
                'type'           => 'one2many',
                'foreign_object' => 'realestate\utility\energy\ConsumptionFileSection',
                'foreign_field'  => 'consumption_file_id',
                'description'    => 'Accounting sections configured for the consumption file.',
                'domain'         => ['condo_id', '=', 'object.condo_id']
            ],

            'consumption_file_lots_ids' => [
                'type'           => 'one2many',
                'foreign_object' => 'realestate\utility\energy\ConsumptionFileLot',
                'foreign_field'  => 'consumption_file_id',
                'description'    => 'Property lot references configured for the consumption file.',
                'domain'         => ['condo_id', '=', 'object.condo_id']
            ]
        ];
    }

    public static function getWorkflow() {
        return [
            'pending' => [
                'description' => 'The consumption file configuration is pending.',
                'icon'        => 'edit',
                'transitions' => [
                    'validate' => [
                        'description' => 'Validate the consumption file configuration.',
                        'policies'    => ['can_validate'],
                        'onbefore'    => 'onbeforeValidate',
                        'status'      => 'ready'
                    ]
                ]
            ],
            'ready' => [
                'description' => 'The consumption file configuration is ready.',
                'icon'        => 'check',
                'transitions' => []
            ]
        ];
    }

    public static function getActions() {
        return array_merge(parent::getActions(), [
            'sync_property_lots' => [
                'description' => 'Create missing property lot references for the consumption file.',
                'policies'    => [],
                'function'    => 'doSyncPropertyLots'
            ]
        ]);
    }

    public static function getPolicies(): array {
        return array_merge(parent::getPolicies(), [
            'can_validate' => [
                'description' => 'Checks that the consumption file configuration is complete.',
                'function'    => 'policyCanValidate'
            ],
            'is_pending' => [
                'description' => 'Checks that the consumption file is still pending.',
                'function'    => 'policyIsPending'
            ]
        ]);
    }

    public static function getOperationPolicies(): array {
        return [
            EQ_R_UPDATE => [
                '*'               => true,
                'condo_id'        => ['is_pending'],
                'suppliership_id' => ['is_pending'],
                'code'            => ['is_pending']
            ]
        ];
    }

    protected static function policyCanValidate($self): array {
        $result = [];
        $self->read([
            'condo_id',
            'suppliership_id',
            'code',
            'consumption_file_sections_ids' => [
                'name',
                'accounting_account_id',
                'condo_id',
                'consumption_file_id'
            ]
        ]);

        foreach($self as $id => $consumptionFile) {
            if(!$consumptionFile['condo_id']) {
                $result[$id]['missing_condo'] = 'A condominium is required.';
            }
            if(!$consumptionFile['suppliership_id']) {
                $result[$id]['missing_suppliership'] = 'A suppliership is required.';
            }
            if(!trim((string) $consumptionFile['code'])) {
                $result[$id]['missing_code'] = 'A consumption file code is required.';
            }

            $has_complete_section = false;
            foreach($consumptionFile['consumption_file_sections_ids'] as $section) {
                if(
                    trim((string) $section['name'])
                    && $section['accounting_account_id']
                    && $section['condo_id']
                    && $section['consumption_file_id']
                ) {
                    $has_complete_section = true;
                    break;
                }
            }
            if(!$has_complete_section) {
                $result[$id]['missing_complete_section'] = 'At least one complete consumption file section is required.';
            }
        }

        return $result;
    }

    protected static function policyIsPending($self): array {
        $result = [];
        $self->read(['status']);
        foreach($self as $id => $consumptionFile) {
            if($consumptionFile['status'] !== 'pending') {
                $result[$id]['file_already_validated'] = 'The condominium, suppliership and code cannot be changed after validation.';
            }
        }
        return $result;
    }

    protected static function onbeforeValidate($self) {
        $self->do('sync_property_lots');
    }

    protected static function doSyncPropertyLots($self) {
        $self->read(['condo_id']);
        foreach($self as $id => $consumptionFile) {
            $existing_lots = ConsumptionFileLot::search([
                    ['consumption_file_id', '=', $id]
                ])
                ->read(['property_lot_id'])
                ->toArray();
            $existing_lot_ids = array_column($existing_lots, 'property_lot_id');

            $propertyLots = PropertyLot::search([
                    ['condo_id', '=', $consumptionFile['condo_id']]
                ])
                ->read(['code']);

            foreach($propertyLots as $property_lot_id => $propertyLot) {
                $code = trim((string) $propertyLot['code']);
                if(!$code || in_array($property_lot_id, $existing_lot_ids, true)) {
                    continue;
                }
                ConsumptionFileLot::create([
                    'consumption_file_id' => $id,
                    'condo_id'            => $consumptionFile['condo_id'],
                    'property_lot_id'     => $property_lot_id,
                    'property_lot_extref' => $code
                ]);
            }
        }
    }

    protected static function calcName($self): array {
        $result = [];
        $self->read([
            'code',
            'suppliership_id' => [
                'supplier_id' => ['name']
            ]
        ]);

        foreach($self as $id => $consumption_file) {
            $supplier = $consumption_file['suppliership_id']['supplier_id'] ?? null;
            if(!$supplier || !$supplier['name'] || !$consumption_file['code']) {
                continue;
            }

            $result[$id] = sprintf('%s - %s', $supplier['name'], $consumption_file['code']);
        }

        return $result;
    }
}
