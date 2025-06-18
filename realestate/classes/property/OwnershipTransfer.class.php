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
                'description'       => "Date at which the ownership transfer took place.",
                'required'          => true
            ],

            'transfer_date' => [
                'type'              => 'date',
                'description'       => "Date at which the ownership transfer took place (notary deed date)."
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

            'property_lots_ids' => [
                'type'              => 'many2many',
                'foreign_object'    => 'realestate\property\PropertyLot',
                'foreign_field'     => 'ownership_transfers_ids',
                'rel_table'         => 'realestate_propertylot_rel_transfer',
                'rel_foreign_key'   => 'lot_id',
                'rel_local_key'     => 'transfer_id',
                'description'       => 'Property Lots that are part of the ownership transfer.'
            ],

            'old_ownership_id' => [
                'type'              => 'many2one',
                'description'       => "The condominium the property lot belongs to.",
                'foreign_object'    => 'realestate\ownership\Ownership'
            ],

            'new_ownership_id' => [
                'type'              => 'many2one',
                'description'       => "The condominium the property lot belongs to.",
                'foreign_object'    => 'realestate\ownership\Ownership'
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

}
