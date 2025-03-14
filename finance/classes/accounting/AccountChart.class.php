<?php
/*
    This file is part of FMT SaaS Software <https://github.com/fmt-saas/fmt>
    Some Rights Reserved, FMT SRL, 2025-2026
    Original author(s): Yesbabylon SA
    Licensed under GNU AGPL 3 license <http://www.gnu.org/licenses/>
*/
namespace finance\accounting;
use equal\orm\Model;

class AccountChart extends Model {

    public static function getName() {
        return "Chart of Accounts";
    }

    public static function getDescription() {
        return "Chart of Accounts is an organisational list holding all company's financial accounts.
        A chart of accounts is created from the template configured for the Managing Agent.";
    }

    public static function getColumns() {

        return [
            'condo_id' => [
                'type'              => 'many2one',
                'description'       => "The condominium the chart of accounts refers to.",
                'foreign_object'    => 'realestate\property\Condominium',
                'readonly'          => true
            ],

            'name' => [
                'type'              => 'string',
                'description'       => "Name of the chart of accounts."
            ],

            'organisation_id' => [
                'type'              => 'many2one',
                'foreign_object'    => 'identity\Organisation',
                'description'       => "The organisation the chart belongs to."
            ],

            'accounts_ids' => [
                'type'              => 'one2many',
                'foreign_object'    => 'finance\accounting\Account',
                'foreign_field'     => 'account_chart_id',
                'description'       => 'Account lines that belong to the chart.',
                'ondetach'          => 'delete'
            ],

            'status' => [
                'type'              => 'string',
                'selection'         => [
                    'draft',
                    'active'
                ],
                'description'       => "Status of the chart, only draft can be modified.",
                'default'           => 'draft'
            ],

        ];
    }

    public static function getWorkflow() {
        return [
            'draft' => [
                'description' => 'Draft chart of accounts, still waiting to be completed for validation.',
                'icon'        => 'draw',
                'transitions' => [
                    'activation' => [
                        'description' => 'Validate and make the chart active. This action is not reversible.',
                        'policies'    => [],
                        'status'    => 'active'
                    ]
                ]
            ]
        ];
    }


    public static function canupdate($self) {
        $self->read(['status']);
        foreach($self as $id => $chart) {
            if($chart['status'] != 'draft') {
                return ['status' => ['not_allowed' => 'Non draft chart cannot be modified.']];
            }
        }
        return parent::canupdate($self);
    }

}