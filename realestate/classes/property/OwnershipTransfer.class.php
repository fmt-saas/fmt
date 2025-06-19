<?php
/*
    This file is part of FMT SaaS Software <https://github.com/fmt-saas/fmt>
    Some Rights Reserved, FMT SRL, 2025-2026
    Original author(s): Yesbabylon SA
    Licensed under GNU AGPL 3 license <http://www.gnu.org/licenses/>
*/
namespace realestate\property;

class OwnershipTransfer extends \equal\orm\Model {
    public static function getColumns() {

        return [

            'condo_id' => [
                'type'              => 'many2one',
                'description'       => "The condominium the property lot belongs to.",
                'foreign_object'    => 'realestate\property\Condominium',
                'required'          => true
            ],

            'request_date' => [
                'type'              => 'date',
                'description'       => "Date at which the first request from the notary was received."
            ],

            'confirmation_date' => [
                'type'              => 'date',
                'description'       => "Date at which the confirmation from the notary was received."
            ],

            'transfer_date' => [
                'type'              => 'date',
                'description'       => "Date at which the ownership transfer is scheduled",
                'help'              => "This date must match the notary deed date."
            ],

            'seller_documents_sent_date' => [
                'type'              => 'date',
                'description'       => "Date at which the ownership transfer documentation has been sent to the notary."
            ],

            'financial_statement_sent_date' => [
                'type'              => 'date',
                'default'           => false,
                'description'       => "Date at which the settlement documents have been sent to the notary."
            ],

            'description' => [
                'type'              => 'string',
                'usage'             => 'text/plain',
                'description'       => "Description of the ownership transfer."
            ],

            'property_lot_id' => [
                'type'              => 'many2one',
                'foreign_object'    => 'realestate\property\PropertyLot',
                'description'       => 'Property Lot that is subject to the transfer.',
                'help'              => 'This serve as first lot for creating the transfer, but can be extended with more lots later on.',
            ],

            'property_lots_ids' => [
                'type'              => 'many2many',
                'foreign_object'    => 'realestate\property\PropertyLot',
                'foreign_field'     => 'ownership_transfers_ids',
                'rel_table'         => 'realestate_propertylot_rel_transfer',
                'rel_foreign_key'   => 'lot_id',
                'rel_local_key'     => 'transfer_id',
                'description'       => 'Property Lots that are part of the ownership transfer.',
                'domain'            => [['condo_id', '=', 'object.condo_id'], ['active_ownership_id', '=', 'object.old_ownership_id']]
            ],

            'old_ownership_id' => [
                'type'              => 'many2one',
                'description'       => "The condominium the property lot belongs to.",
                'foreign_object'    => 'realestate\ownership\Ownership',
                'domain'            => ['condo_id', '=', 'object.condo_id']
            ],

            'is_existing_new_ownership' => [
                'type'              => 'boolean',
                'description'       => "The condominium the property lot belongs to.",
                'default'           => false
            ],

            'new_ownership_id' => [
                'type'              => 'many2one',
                'description'       => "The condominium the property lot belongs to.",
                'foreign_object'    => 'realestate\ownership\Ownership',
                'domain'            => [['condo_id', '=', 'object.condo_id'], ['id', '<>', 'object.old_ownership_id']],
                'visible'           => ['is_existing_new_ownership', '=', true]
            ],

            'status' => [
                'type'              => 'string',
                'selection'         => [
                    'draft',
                    'open',
                    'confirmed',
                    'accounting_pending',
                    'closed'
                ],
                'default'     => 'draft',
                'description' => 'Status of the ownership transfer.',
            ],

        ];
    }

    public static function getWorkflow() {
        return [
            'draft' => [
                'description' => 'Draft ownership transfer, not yet validated.',
                'icon'        => 'draft',
                'transitions' => [
                    'open' => [
                        'description' => 'Update the document to `pending`.',
                        'policies'    => [],
                        'status'      => 'open'
                    ]
                ]
            ],
            'open' => [
                'description' => 'Validated document, waiting to be sent.',
                'icon'        => 'hourglass_top',
                'transitions' => [
                    'send' => [
                        'description' => 'Update the document to `processed`.',
                        'policies'    => [],
                        'status'      => 'seller_documents_sent'
                    ]
                ]
            ],
            'seller_documents_sent' => [
                'description' => 'Validated document, waiting to be sent.',
                'icon'        => 'hourglass_top',
                'transitions' => [
                    'confirm' => [
                        'description' => 'Update the document to `processed`.',
                        'policies'    => [],
                        'status'      => 'confirmed'
                    ],
                    'to_complete' => [
                        'description' => 'Some additional documents are required, step back to `open`.',
                        'policies'    => [],
                        'status'      => 'open'
                    ]
                ]
            ],
            'confirmed' => [
                'description' => 'Validated document, waiting to be processed.',
                'icon'        => 'hourglass_top',
                'transitions' => [
                    'send' => [
                        'description' => 'Update the document to `processed`.',
                        'policies'    => [],
                        'status'      => 'financial_statement_sent'
                    ]
                ]
            ],
            'financial_statement_sent' => [
                'description' => 'Documentation sent, waiting for the notary .',
                'icon'        => 'hourglass_top',
                'transitions' => [
                    'settle' => [
                        'description' => 'Mark the ownership transfer as settled.',
                        'help'        => 'The notary deed has been signed and the notary has sent the settlement documents to the accounting department.',
                        'policies'    => [],
                        'status'      => 'accounting_pending'
                    ],
                    'to_complete' => [
                        'description' => 'Some additional documents are required, step back to `confirmed`.',
                        'policies'    => [],
                        'status'      => 'confirmed'
                    ]
                ]
            ],
            'accounting_pending' => [
                'description' => 'Ownership transfer is pending, waiting for the notary deed to complete accounting settlement.',
                'icon'        => 'hourglass_top',
                'transitions' => [
                    'close' => [
                        'description' => 'Update the ownership transfer to `closed`.',
                        'policies'    => [],
                        'status'      => 'closed'
                    ]
                ]
            ],
            'closed' => [
                'description' => 'Ownership transfer is closed, no further actions can be taken.'
            ]
        ];
    }


    public static function onchange($event, $values) {
        $result = [];
        // synchronize ownership & property lots
        // #memo - we must be able to assign any ownership (not only active ones)
        if(array_key_exists('old_ownership_id', $event)) {
            if($event['old_ownership_id']) {
                $propertyOwnerships = PropertyLotOwnership::search([['ownership_id', '=', $event['old_ownership_id']]])->read(['property_lot_id'])->get(true);
                $property_lots_ids = array_map(function ($a) {return $a['property_lot_id'];}, $propertyOwnerships);
                if(!$values['property_lot_id'] || !in_array($values['property_lot_id'], $property_lots_ids) ) {
                    $result['property_lot_id'] = [
                        'domain' => [['condo_id', '=', $values['condo_id']], ['id', 'in', $property_lots_ids]]
                    ];
                }
                $result['property_lots_ids'] = $property_lots_ids;
            }
            else {
                $result['old_ownership_id'] = [
                    'domain' => ['condo_id', '=', $values['condo_id']]
                ];
                $result['property_lot_id'] = [
                    'domain' => ['condo_id', '=', $values['condo_id']]
                ];
                $result['property_lots_ids'] = [];
            }
        }
        if(array_key_exists('property_lot_id', $event)) {
            if($event['property_lot_id']) {
                $propertyOwnerships = PropertyLotOwnership::search([['property_lot_id', '=', $event['property_lot_id']]])->read(['ownership_id'])->get(true);
                $ownerships_ids = array_map(function ($a) {return $a['ownership_id'];}, $propertyOwnerships);
                if(!$values['old_ownership_id'] || !in_array($values['old_ownership_id'], $ownerships_ids) ) {
                    $result['old_ownership_id'] = [
                        'domain' => [['condo_id', '=', $values['condo_id']], ['id', 'in', $ownerships_ids]]
                    ];
                }
            }
            else {
                $result['old_ownership_id'] = [
                    'domain' => ['condo_id', '=', $values['condo_id']]
                ];
                $result['property_lot_id'] = [
                    'domain' => ['condo_id', '=', $values['condo_id']]
                ];
            }
        }
        return $result;
    }

}
