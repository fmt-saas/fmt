<?php
/*
    Developed by Yesbabylon - https://yesbabylon.com
    (c) 2025-2026 Yesbabylon SA
    Licensed under the GNU AGPL v3 License - https://www.gnu.org/licenses/agpl-3.0.html
*/

namespace realestate\funding;

use documents\export\ExportingTask;
use documents\export\ExportingTaskLine;
use finance\accounting\FiscalYear;
use fmt\setting\Setting;
use realestate\ownership\Ownership;
use realestate\ownership\OwnershipCommunicationPreference;
use realestate\sale\pay\Funding;
use sale\price\Price;

class PaymentReminder extends \sale\pay\PaymentReminder {

    public static function getDescription(): string {
        return "A funding reminder streamlines the process of alerting customers when a funding due date has passed and the corresponding payment remains outstanding.";
    }

    public static function getColumns(): array {
        return [

            'condo_id' => [
                'type'              => 'many2one',
                'description'       => "The condominium the reminder relates to.",
                'foreign_object'    => 'realestate\property\Condominium',
                'readonly'          => true,
                'required'          => true
            ],

            'emission_date' => [
                'type'              => 'date',
                'description'       => "Date at which the reminder was emitted.",
                'default'           => fn() => time(),
                'dependents'        => ['due_amount']
            ],

            'due_amount' => [
                'type'              => 'computed',
                'result_type'       => 'float',
                'usage'             => 'amount/money:2',
                'description'       => "The amount that is due from the funding.",
                'store'             => true,
                'function'          => 'calcDueAmount'
            ],

            // #todo
            'due_date' => [
                'type'              => 'date',
                'usage'             => 'date/plain',
                'description'       => "General Deadline before which payments are requested to owners."
            ],

            // #memo - funding_id is useless here - only to override `required` property
            'funding_id' => [
                'type'              => 'many2one',
                'description'       => 'The funding reminder relates to.',
                'help'              => "#memo - Funding is stored at PaymentReminderOwnerLine level.",
                'foreign_object'    => 'sale\pay\Funding',
                'readonly'          => true
            ],

            // #todo - calc based on PaymentReminderOwnerLine
            'fundings_ids' => [
                'type'              => 'many2many',
                'foreign_object'    => 'realestate\sale\pay\Funding',
                'foreign_field'     => 'payment_reminders_ids',
                'rel_table'         => 'realestate_ownership_transfer_rel_documents',
                'rel_foreign_key'   => 'funding_id',
                'rel_local_key'     => 'payment_reminder_id'
            ],

            'payment_reminder_owners_ids' => [
                'type'              => 'one2many',
                'description'       => "Owners present in the reminder.",
                'foreign_object'    => 'realestate\funding\PaymentReminderOwner',
                'foreign_field'     => 'payment_reminder_id'
            ],

            'payment_reminder_owner_lines_ids' => [
                'type'              => 'one2many',
                'description'       => "Reminder owner lines present in the reminder.",
                'foreign_object'    => 'realestate\funding\PaymentReminderOwnerLine',
                'foreign_field'     => 'payment_reminder_id'
            ],

            'exporting_tasks_ids' => [
                'type'              => 'one2many',
                'description'       => "Reference to the tasks for exporting paper mails for payment reminder, if any.",
                'help'              => "This is a helper relation to allow generic handling in views.",
                'foreign_object'    => 'documents\export\ExportingTask',
                'foreign_field'     => 'object_id',
                'domain'            => [
                    ['object_class', '=', 'realestate\funding\PaymentReminder']
                ]
            ],

            'reminders_exporting_task_id' => [
                'type'              => 'many2one',
                'description'       => "Reference to the task for exporting paper mails for payment reminder, if any.",
                'foreign_object'    => 'documents\export\ExportingTask'
            ],

            'payment_reminder_correspondences_ids' => [
                'type'              => 'one2many',
                'description'       => "Correspondences generated for the reminder.",
                'foreign_object'    => 'realestate\funding\PaymentReminderCorrespondence',
                'foreign_field'     => 'payment_reminder_id'
            ],

            'status' => [
                'type'              => 'string',
                'selection'         => [
                    'draft',
                    'pending',
                    'ignored',
                    'sent'
                ],
                'description'       => 'The current status of the reminder.',
                'help'              => "The reminders are first created and then are published only if candidate to be sent.",
                'default'           => 'draft'
            ]

        ];
    }

    public static function getActions(): array {
        return array_merge(parent::getActions(), [
            'generate_reminder' => [
                'description'   => 'Clear and regenerate reminder lines according to the current overdue fundings.',
                'policies'      => ['can_generate_reminder'],
                'function'      => 'doGenerateReminder'
            ],
            'generate_payment_reminder_correspondences' => [
                'description'   => 'Generate individual correspondences according to ownership communication preferences.',
                'policies'      => [],
                'function'      => 'doGeneratePaymentReminderCorrespondences'
            ],
            'send_payment_reminders' => [
                'description'   => 'Queue sending and/or exports according to generated correspondences.',
                'policies'      => [],
                'function'      => 'doSendPaymentReminders'
            ]
        ]);
    }

    public static function getPolicies(): array {
        return array_merge(parent::getPolicies(), [
            'can_generate_reminder' => [
                'description' => 'Verifies that the reminder can still be regenerated.',
                'function'    => 'policyCanGenerateReminder'
            ]
        ]);
    }

    protected static function policyCanGenerateReminder($self): array {
        $result = [];

        $self->read(['status']);
        foreach($self as $id => $paymentReminder) {
            if(!in_array($paymentReminder['status'], ['draft', 'pending'], true)) {
                $result[$id] = [
                    'invalid_status' => 'Only draft or pending reminders can be regenerated.'
                ];
            }
        }

        return $result;
    }

    public static function getWorkflow() {
        return [
            'draft' => [
                'description' => 'Reminder being completed, waiting to be validated.',
                'icon'        => 'edit',
                'transitions' => [
                    'ignore' => [
                        'description' => 'Update the Reminder to `ignored`.',
                        'onafter'     => 'onafterIgnore',
                        'status'      => 'ignored'
                    ],
                    'validate' => [
                        'description' => 'Update the Reminder to `pending`.',
                        'onafter'     => 'onafterValidate',
                        'status'      => 'pending'
                    ]
                ]
            ],
            'pending' => [
                'description' => 'Pending reminder, waiting to be sent.',
                'icon'        => 'edit',
                'transitions' => [
                    'ignore' => [
                        'description' => 'Update the Reminder to `ignored`.',
                        'onafter'     => 'onafterIgnore',
                        'status'      => 'ignored'
                    ],
                    'send' => [
                        'description' => 'Update the Reminder to `sent`.',
                        'onbefore'    => 'onbeforeSend',
                        'onafter'     => 'onafterSend',
                        'status'      => 'sent'
                    ]
                ]
            ]
        ];
    }

    protected static function doGenerateReminder($self): void {
        $self->read(['condo_id']);

        $now = strtotime('today');

        foreach($self as $id => $paymentReminder) {

            PaymentReminderOwnerLine::search(['payment_reminder_id', '=', $id])->delete(true);
            PaymentReminderOwner::search(['payment_reminder_id', '=', $id])->delete(true);

            $condo_id = $paymentReminder['condo_id'];

            $fiscalYear = FiscalYear::search([
                        ['condo_id', '=', $condo_id],
                        ['date_from', '<=', $now],
                    ],
                    ['sort' => ['date_from' => 'desc'], 'limit' => 1
                ])
                ->read(['date_from'])
                ->first();

            if(!$fiscalYear) {
                continue;
            }

            $date_from = $fiscalYear['date_from'] ?? $now;

            $overdueFundings = Funding::search([
                    ['status', 'in', ['pending', 'debit_balance']],
                    ['condo_id', '=', $condo_id],
                    ['funding_type', 'in', ['fund_request', 'expense_statement', 'misc_operation']],
                    ['due_date', '<=', $now],
                    ['ownership_id', '<>', null]
                ])
                ->read(['condo_id', 'ownership_id', 'due_date', 'due_amount', 'remaining_amount']);

            if($overdueFundings->count() <= 0) {
                continue;
            }

            $created_lines = 0;
            $map_ownership_balances = [];
            $map_payment_reminder_ownership = [];

            foreach($overdueFundings as $funding_id => $funding) {

                $ownership_id = $funding['ownership_id'];

                $previousReminderOwner = PaymentReminderOwner::search([
                        ['condo_id', '=', $condo_id],
                        ['payment_reminder_id', '<>', $id],
                        ['ownership_id', '=', $ownership_id],
                        ['due_date', '>=', $now],
                        ['status', 'in', ['pending', 'ignored', 'sent']]
                    ])
                    ->first();

                if($previousReminderOwner) {
                    continue;
                }

                // count how many times a reminder has been sent for the related funding
                $previousReminderOwnerLines = PaymentReminderOwnerLine::search([
                        ['status', '=', 'sent'],
                        ['funding_id', '=', $funding_id],
                        ['due_date', '<', $now]
                    ])
                    ->read(['due_date']);

                if(!isset($map_ownership_balances[$ownership_id])) {
                    $map_ownership_balances[$ownership_id] = 0;

                    $data = \eQual::run('get', 'finance_accounting_ownerAccountStatement_collect', [
                        'ownership_id' => $ownership_id,
                        'date_from'    => $date_from,
                        'date_to'      => $now
                    ]);

                    if(count($data)) {
                        $map_ownership_balances[$ownership_id] = end($data)['balance'] ?? 0;
                    }
                }

                $current_balance = $map_ownership_balances[$ownership_id];

                if($current_balance <= 0) {
                    continue;
                }

                if($funding['remaining_amount'] <= 0) {
                    continue;
                }

                if(!isset($map_payment_reminder_ownership[$ownership_id])) {
                    $map_payment_reminder_ownership[$ownership_id] = PaymentReminderOwner::create([
                            'condo_id'              => $condo_id,
                            'ownership_id'          => $ownership_id,
                            'payment_reminder_id'   => $id,
                            'due_balance'           => $current_balance,
                            'due_date'              => $now + (15 * 86400)
                        ])
                        ->first();
                }

                $paymentReminderOwner = $map_payment_reminder_ownership[$ownership_id];
                $reminder_level = $previousReminderOwnerLines->count() + 1;

                // get the reminder amount using settings "realestate.features.payment_reminder.*"
                $reminder_amount = 0.0;
                $reminder_lvl_product_id = Setting::get_value('realestate', 'features', "payment_reminder.level_$reminder_level.product_id");
                if($reminder_lvl_product_id) {
                    $price_id = \eQual::run('get', 'realestate_property_Condominium_product-price', [
                        'id'            => $condo_id,
                        'product_id'    => $reminder_lvl_product_id
                    ]);

                    if($price_id) {
                        $price = Price::id($price_id)
                            ->read(['price_vat'])
                            ->first();

                        $reminder_amount += $price['price_vat'];
                    }
                }

                PaymentReminderOwnerLine::create([
                    'condo_id'                  => $condo_id,
                    'ownership_id'              => $ownership_id,
                    'funding_id'                => $funding_id,
                    'payment_reminder_id'       => $id,
                    'payment_reminder_owner_id' => $paymentReminderOwner['id'],
                    'due_amount'                => $funding['remaining_amount'],
                    'reminder_amount'           => $reminder_amount,
                    'due_date'                  => $now + (15 * 86400),
                    'reminder_level'            => $reminder_level
                ]);

                ++$created_lines;
            }

            if($created_lines > 0) {
                self::id($id)->update(['emission_date' => time()]);
            }
        }
    }

    protected static function onbeforeSend($self) {
        foreach($self as $id => $paymentReminder) {
            $today = strtotime('today');
            $due_date = $today + (86400 * 15);
            PaymentReminderOwner::search([
                    ['payment_reminder_id','=', $id],
                    ['status', '<>', 'ignored']
                ])
                ->update([
                    'due_date'      => $due_date
                ]);
            PaymentReminderOwnerLine::search([
                    ['payment_reminder_id','=', $id],
                    ['status', '<>', 'ignored']
                ])
                ->update([
                    'issue_date'    => $today,
                    'due_date'      => $due_date
                ]);
        }

        $self->do('send_payment_reminders');
    }

    protected static function onafterSend($self) {
        foreach($self as $id => $paymentReminder) {
            PaymentReminderOwner::search([
                    ['payment_reminder_id','=', $id],
                    ['status', '<>', 'ignored']
                ])
                ->update(['status'   => 'sent']);

            PaymentReminderOwnerLine::search([
                    ['payment_reminder_id','=', $id],
                    ['status', '<>', 'ignored']
                ])
                ->update(['status'   => 'sent']);
        }
    }

    protected static function onafterIgnore($self) {
        foreach($self as $id => $paymentReminder) {
            PaymentReminderOwner::search(['payment_reminder_id','=', $id])
                ->update(['status'   => 'ignored']);

            PaymentReminderOwnerLine::search(['payment_reminder_id','=', $id])
                ->update(['status'   => 'ignored']);
        }
    }

    protected static function onafterValidate($self) {
        foreach($self as $id => $paymentReminder) {
            PaymentReminderOwner::search([
                    ['payment_reminder_id','=', $id],
                    ['status', '<>', 'ignored']
                ])
                ->update(['status'   => 'pending']);

            PaymentReminderOwnerLine::search([
                    ['payment_reminder_id','=', $id],
                    ['status', '<>', 'ignored']
                ])
                ->update(['status'   => 'pending']);
        }
    }

    protected static function doGeneratePaymentReminderCorrespondences($self): void {
        $self->read(['condo_id', 'payment_reminder_owners_ids' => ['ownership_id', 'status']]);

        foreach($self as $id => $paymentReminder) {
            PaymentReminderCorrespondence::search(['payment_reminder_id', '=', $id])->delete(true);

            foreach($paymentReminder['payment_reminder_owners_ids'] as $payment_reminder_owner_id => $paymentReminderOwner) {
                if(($paymentReminderOwner['status'] ?? null) === 'ignored') {
                    continue;
                }

                $ownership_id = $paymentReminderOwner['ownership_id'] ?? null;

                $ownership = Ownership::id($ownership_id)
                    ->read(['representative_owner_id'])
                    ->first();

                if(!$ownership || !$ownership['representative_owner_id']) {
                    continue;
                }

                $communication_methods = [
                    'email'                     => false,
                    'postal'                    => false,
                    'postal_registered'         => false,
                    'postal_registered_receipt' => false
                ];

                $communicationPreference = OwnershipCommunicationPreference::search([
                        ['condo_id', '=', $paymentReminder['condo_id']],
                        ['ownership_id', '=', $ownership_id],
                        ['communication_reason', '=', 'fund_request']
                    ])
                    ->read([
                        'has_channel_email',
                        'has_channel_postal',
                        'has_channel_postal_registered',
                        'has_channel_postal_registered_receipt'
                    ])
                    ->first();

                if($communicationPreference) {
                    $communication_methods = [
                        'email'                     => $communicationPreference['has_channel_email'],
                        'postal'                    => $communicationPreference['has_channel_postal'],
                        'postal_registered'         => $communicationPreference['has_channel_postal_registered'],
                        'postal_registered_receipt' => $communicationPreference['has_channel_postal_registered_receipt']
                    ];
                }

                if(!in_array(true, $communication_methods, true)) {
                    $communication_methods['postal_registered'] = true;
                }

                foreach($communication_methods as $communication_method => $communication_method_flag) {
                    if(!$communication_method_flag) {
                        continue;
                    }

                    PaymentReminderCorrespondence::create([
                        'condo_id'                      => $paymentReminder['condo_id'],
                        'payment_reminder_id'           => $id,
                        'payment_reminder_owner_id'     => $payment_reminder_owner_id,
                        'ownership_id'                  => $ownership_id,
                        'owner_id'                      => $ownership['representative_owner_id'],
                        'communication_method'          => $communication_method
                    ]);
                }
            }
        }
    }

    protected static function doSendPaymentReminders($self, $cron): void {
        $self
            ->do('generate_payment_reminder_correspondences')
            ->read([
                'name',
                'condo_id',
                'reminders_exporting_task_id',
                'payment_reminder_correspondences_ids' => ['communication_method', 'ownership_id']
            ]);

        foreach($self as $id => $paymentReminder) {
            if($paymentReminder['reminders_exporting_task_id']) {
                ExportingTask::id($paymentReminder['reminders_exporting_task_id'])->delete(true);
            }

            $map_ignored_ownership_ids = [];
            $ignoredPaymentReminderOwners = PaymentReminderOwner::search([
                    ['payment_reminder_id', '=', $id],
                    ['status', '=', 'ignored']
                ])
                ->read(['ownership_id']);

            foreach($ignoredPaymentReminderOwners as $ignoredPaymentReminderOwner) {
                if($ignoredPaymentReminderOwner['ownership_id']) {
                    $map_ignored_ownership_ids[$ignoredPaymentReminderOwner['ownership_id']] = true;
                }
            }

            $map_communication_methods = [];
            foreach($paymentReminder['payment_reminder_correspondences_ids'] as $paymentReminderCorrespondence) {
                if(isset($map_ignored_ownership_ids[$paymentReminderCorrespondence['ownership_id']])) {
                    continue;
                }

                $map_communication_methods[$paymentReminderCorrespondence['communication_method']] = true;
            }

            if(isset($map_communication_methods['email'])) {
                $cron->schedule(
                    "realestate.paymentreminder.send-reminders.{$id}",
                    time() + (5 * 60),
                    'realestate_funding_PaymentReminder_send-reminders',
                    ['id' => $id]
                );
            }

            if(count(array_diff(array_keys($map_communication_methods), ['email'])) > 0) {
                $exportingTask = ExportingTask::create([
                        'name'          => "Export des courriers du rappel de paiement",
                        'condo_id'      => $paymentReminder['condo_id'],
                        'object_class'  => static::class,
                        'object_id'     => $id
                    ])
                    ->first();

                foreach($map_communication_methods as $communication_method => $flag) {
                    if($communication_method === 'email') {
                        continue;
                    }

                    ExportingTaskLine::create([
                        'exporting_task_id' => $exportingTask['id'],
                        'name'              => "Export du rappel - {$communication_method}",
                        'controller'        => 'realestate_funding_PaymentReminder_export-reminders',
                        'params'            => json_encode([
                            'id'                    => $id,
                            'communication_method'  => $communication_method
                        ])
                    ]);
                }

                self::id($id)->update([
                    'reminders_exporting_task_id' => $exportingTask['id']
                ]);
            }
        }
    }

    protected static function calcDueAmount($self) {
        $result = [];
        $self->read(['payment_reminder_owners_ids' => ['due_amount']]);
        foreach($self as $id => $paymentReminderOwner) {
            $result[$id] = 0.0;
            foreach($paymentReminderOwner['payment_reminder_owners_ids'] as $payment_reminder_owner_id => $paymentReminderOwner) {
                $result[$id] += $paymentReminderOwner['due_amount'];
            }
        }
        return $result;
    }



}
