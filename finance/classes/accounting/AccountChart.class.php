<?php
/*
    Developed by Yesbabylon - https://yesbabylon.com
    (c) 2025-2026 Yesbabylon SA
    Licensed under the GNU AGPL v3 License - https://www.gnu.org/licenses/agpl-3.0.html
*/
namespace finance\accounting;
use equal\orm\Model;
use realestate\property\Apportionment;

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
                'ondetach'          => 'delete',
                'domain'            => [['condo_id', '=', 'object.condo_id'], ['condo_id', '<>', null]]
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

    public static function getPolicies(): array {
        return [
            'is_draft' => [
                'description' => 'Verifies that a chart of account is a draft.',
                'function'    => 'policyIsDraft'
            ]
        ];
    }

    public static function getActions() {
        return [
            'import_accounts' => [
                'description'   => 'Import accounts from a given template chart of accounts.',
                'policies'      => ['is_draft'],
                'function'      => 'doImportAccounts'
            ]
        ];
    }

    public static function policyIsDraft($self) {
        $result = [];
        $self->read(['status']);
        foreach($self as $id => $accountChart) {
            if($accountChart['status'] != 'draft') {
                $result[$id] = [
                    'invalid_status' => 'Chart of accounts is currently active.'
                ];
                continue;
            }
        }
        return $result;
    }

    public static function getWorkflow() {
        return [
            'draft' => [
                'description' => 'Draft chart of accounts, still waiting to be completed for validation.',
                'icon'        => 'draw',
                'transitions' => [
                    'activation' => [
                        'description'   => 'Validate and make the chart active. This action is not reversible.',
                        'onbefore'      => 'onbeforeActivation',
                        'policies'      => [],
                        'status'        => 'active'
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


    protected static function doImportAccounts($self, $values) {
        $self->read(['condo_id']);

        foreach($self as $id => $accountChart) {
            $template = AccountChartTemplate::id($values['chart_template_id'])
                ->read([
                    'accounts_ids' => [
                        'name',
                        'code',
                        'description',
                        'level',
                        'account_class',
                        'account_type',
                        'account_nature',
                        'account_category',
                        'parent_account_id',
                        'is_visible',
                        'is_control_account',
                        'is_tier_balance',
                        'operation_assignment',
                        'is_apportionment_possible',
                        'tenant_share',
                        'owner_share',
                        'apportionment_code'
                    ]
                ])
                ->first();

            if(!$template) {
                throw new \Exception('unknown_template', EQ_ERROR_INVALID_PARAM);
            }

            // remove any previously existing accounts attached to the chart
            Account::search([['account_chart_id', '=', $id]])->delete(true);

            // create apportionment map
            $map_apportionments = [];
            $apportionments = Apportionment::search(['condo_id', '=', $accountChart['condo_id']])->read(['code']);

            foreach($apportionments as $apportionment_id => $apportionment) {
                $map_apportionments[$apportionment['code']] = $apportionment_id;
            }

            foreach($template['accounts_ids'] as $account_id => $account) {
                $item = [
                        'condo_id'                  => $accountChart['condo_id'],
                        'account_chart_id'          => $id,
                        'name'                      => $account['name'],
                        'code'                      => $account['code'],
                        'description'               => $account['description'],
                        'level'                     => $account['level'],
                        'account_class'             => $account['account_class'],
                        'account_type'              => $account['account_type'],
                        'account_nature'            => $account['account_nature'],
                        'account_category'          => $account['account_category'],
                        'is_visible'                => $account['is_visible'],
                        'is_control_account'        => $account['is_control_account'],
                        'is_tier_balance'           => $account['is_tier_balance'],
                        'operation_assignment'      => $account['operation_assignment'],
                        'is_apportionment_possible' => $account['is_apportionment_possible'],
                        'tenant_share'              => $account['tenant_share'],
                        'owner_share'               => $account['owner_share']
                    ];

                if(isset($map_apportionments[$account['apportionment_code']])) {
                    $item['apportionment_id'] = $map_apportionments[$account['apportionment_code']];
                }
                Account::create($item);
            }

        }
    }

    protected static function onbeforeActivation($self) {
        $self->read(['condo_id', 'accounts_ids']);
        foreach($self as $id => $accountChart) {
            // force computation of parent_account_id fields
            Account::ids($accountChart['accounts_ids'])->read(['parent_account_id']);
        }
    }

}
