<?php
/*
    Developed by Yesbabylon - https://yesbabylon.com
    (c) 2025-2026 Yesbabylon SA
    Licensed under the GNU AGPL v3 License - https://www.gnu.org/licenses/agpl-3.0.html
*/

namespace realestate\funding;

use documents\export\ExportingTask;
use documents\export\ExportingTaskLine;
use realestate\ownership\Ownership;
use realestate\ownership\OwnershipCommunicationPreference;

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
                'description'       => "Date at which the reminder was emitted."
            ],

            'due_amount' => [
                'type'              => 'computed',
                'result_type'       => 'float',
                'usage'             => 'amount/money:2',
                'description'       => "The amount that is due from the funding.",
                'store'             => true,
                'function'          => 'calcDueAmount'
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

    public static function getWorkflow() {
        return [
            'pending' => [
                'description' => 'Balance being completed, waiting to be validated.',
                'icon'        => 'edit',
                'transitions' => [
                    'send' => [
                        'description' => 'Update the Balance to `validated`.',
                        'onbefore'    => 'onbeforeSend',
                        'onafter'     => 'onafterSend',
                        'status'      => 'validated'
                    ]
                ]
            ]
        ];
    }

    protected static function onbeforeSend($self) {
        foreach($self as $id => $paymentReminder) {
            $today = strtotime('today');
            PaymentReminderOwnerLine::search(['payment_reminder_id','=', $id])
                ->update([
                    'issue_date'                => $today,
                    'due_date'                  => $today + (86400 * 15)
                ]);
        }

        $self->do('send_payment_reminders');
    }

    protected static function onafterSend($self) {
        foreach($self as $id => $paymentReminder) {
            PaymentReminderOwnerLine::search(['payment_reminder_id','=', $id])
                ->update([
                    'payment_reminder_status'   => 'sent'
                ]);
        }
    }

    protected static function doGeneratePaymentReminderCorrespondences($self): void {
        $self->read(['condo_id', 'payment_reminder_owners_ids' => ['ownership_id']]);

        foreach($self as $id => $paymentReminder) {
            PaymentReminderCorrespondence::search(['payment_reminder_id', '=', $id])->delete(true);

            $map_ownership_ids = [];
            foreach($paymentReminder['payment_reminder_owners_ids'] as $paymentReminderOwner) {
                $ownership_id = $paymentReminderOwner['ownership_id'] ?? null;
                if(!$ownership_id) {
                    continue;
                }
                $map_ownership_ids[$ownership_id] = true;
            }

            foreach(array_keys($map_ownership_ids) as $ownership_id) {
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
                        'condo_id'              => $paymentReminder['condo_id'],
                        'payment_reminder_id'   => $id,
                        'ownership_id'          => $ownership_id,
                        'owner_id'              => $ownership['representative_owner_id'],
                        'communication_method'  => $communication_method
                    ]);
                }
            }
        }
    }

    protected static function doSendPaymentReminders($self, $cron): void {
        $self->read([
            'name',
            'condo_id',
            'reminders_exporting_task_id',
            'payment_reminder_correspondences_ids' => ['communication_method']
        ]);

        foreach($self as $id => $paymentReminder) {
            if($paymentReminder['reminders_exporting_task_id']) {
                ExportingTask::id($paymentReminder['reminders_exporting_task_id'])->delete(true);
            }

            $map_communication_methods = [];
            foreach($paymentReminder['payment_reminder_correspondences_ids'] as $paymentReminderCorrespondence) {
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
                        'name'          => "{$paymentReminder['name']} - Export des courriers du rappel de paiement",
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
                        'name'              => "{$paymentReminder['name']} - Export du rappel - {$communication_method}",
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


    /*
    // upon validation
                Funding::create([
                        'condo_id'                          => $expenseStatement['condo_id']['id'],
                        'description'                       => $expenseStatement['name'],
                        'funding_type'                      => 'due_balance',
                        'expense_statement_id'              => $id,
                        'ownership_id'                      => $ownership_id,
                        'bank_account_id'                   => $expenseStatement['statement_bank_account_id'],
                        'accounting_account_id'             => $ownershipAccount['id'],
                        'issue_date'                        => $issue_date,
                        'due_date'                          => $due_date,
                        'due_amount'                        => $closing_balance
                    ]);

    */
}
