<?php
/*
    This file is part of FMT SaaS Software <https://github.com/fmt-saas/fmt>
    Some Rights Reserved, FMT SRL, 2025-2026
    Original author(s): Yesbabylon SA
    Licensed under GNU AGPL 3 license <http://www.gnu.org/licenses/>
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