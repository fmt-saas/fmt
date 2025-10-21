<?php
/*
    Developed by Yesbabylon - https://yesbabylon.com
    (c) 2025-2026 Yesbabylon SA
    Licensed under the GNU AGPL v3 License - https://www.gnu.org/licenses/agpl-3.0.html
*/
namespace finance\accounting;
use equal\orm\Model;

class BalanceUpdateRequest extends Model {

    public static function getDescription() {
        return "Balance update requests are used to handle current balance updates in a way that guarantees atomicity for concurrent changes.";
    }

    public static function getColumns() {
        return [
            'condo_id' => [
                'type'              => 'many2one',
                'description'       => "The condominium the accounting entry refers to.",
                'foreign_object'    => 'realestate\property\Condominium',
                'readonly'          => true,
                'required'          => true
            ],

            'balance_id' => [
                'type'              => 'many2one',
                'foreign_object'    => 'finance\accounting\CurrentBalance',
                'readonly'          => true,
                'required'          => true
            ],

            'accounting_entry_id' => [
                'type'              => 'many2one',
                'description'       => "The accounting entry the request refers to.",
                'foreign_object'    => 'finance\accounting\AccountingEntry',
                'readonly'          => true,
                'required'          => true
            ],

            'status' => [
                'type'              => 'string',
                'selection'         => [
                    'pending',
                    'processed'
                ],
                'default'           => 'pending',
                'description'       => 'Status of the accounting entry.',
                'help'              => 'Once an accounting entry has been validated, it cannot be removed. It can however, be cancelled through a reverse entry.'
            ]

        ];
    }

    public static function getActions() {
        return [
            'process' => [
                'description'   => 'Perform the update of the balance according to given accounting entry.',
                'policies'      => ['can_be_processed'],
                'function'      => 'doProcess'
            ]
        ];
    }

    public static function getPolicies(): array {
        return [
            'can_be_processed' => [
                'description' => 'Verifies that an accounting entry can be cancelled (validated and not already cancelled).',
                'function'    => 'policyCanBeProcessed'
            ]
        ];
    }

    public static function policyCanBeProcessed($self): array {
        $result = [];
        $self->read(['status']);
        foreach($self as $id => $accountingEntry) {
            if($accountingEntry['status'] != 'pending') {
                $result[$id] = [
                        'invalid_status' => 'Request already processed.'
                    ];
                continue;
            }
        }
        return $result;
    }

    public static function doProcess($self) {
        $self->read(['status', 'balance_id', 'accounting_entry_id' => ['entry_lines_ids' => ['account_id', 'debit', 'credit']]]);
        foreach($self as $id => $request) {
            if($request['status'] != 'pending') {
                continue;
            }
            foreach($request['accounting_entry_id']['entry_lines_ids'] ?? [] as $entry_line_id => $entryLine) {
                CurrentBalance::id($request['balance_id'])
                    ->do('update_account', [
                        'account_id' => $entryLine['account_id'],
                        'debit'      => $entryLine['debit'],
                        'credit'     => $entryLine['credit']
                    ]);
            }
            self::id($id)->update(['status' => 'processed']);
        }
    }

}