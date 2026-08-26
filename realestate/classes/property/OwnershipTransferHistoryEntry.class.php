<?php
/*
    Developed by Yesbabylon - https://yesbabylon.com
    (c) 2025-2026 Yesbabylon SA
    Licensed under the GNU AGPL v3 License - https://www.gnu.org/licenses/agpl-3.0.html
*/
namespace realestate\property;

class OwnershipTransferHistoryEntry extends \equal\orm\Model {

    public static function getColumns() {
        return [

            'ownership_transfer_id' => [
                'type'              => 'many2one',
                'foreign_object'    => 'realestate\property\OwnershipTransfer',
                'description'       => 'Ownership transfer from which the email was sent.',
                'required'          => true,
                'readonly'          => true,
                'ondelete'          => 'cascade'
            ],

            'email_id' => [
                'type'              => 'many2one',
                'foreign_object'    => 'communication\email\Email',
                'description'       => 'Email created for the ownership transfer correspondence.',
                'required'          => true,
                'readonly'          => true,
                'dependents'        => ['recipients']
            ],

            'sent_at' => [
                'type'              => 'datetime',
                'description'       => 'Date and time at which the correspondence was queued for sending.',
                'default'           => fn() => time(),
                'required'          => true,
                'readonly'          => true
            ],

            'transfer_status' => [
                'type'              => 'string',
                'selection'         => [
                    'pending',
                    'open',
                    'seller_documents_sent',
                    'confirmed',
                    'financial_statement_sent',
                    'settled',
                    'closed'
                ],
                'description'       => 'Transfer status when the correspondence was sent.',
                'required'          => true,
                'readonly'          => true
            ],

            'recipients' => [
                'type'              => 'computed',
                'result_type'       => 'string',
                'description'       => 'Comma-separated list of email recipients.',
                'function'          => 'calcRecipients',
                'store'             => true,
                'instant'           => true,
                'readonly'          => true
            ]

        ];
    }

    protected static function calcRecipients($self) {
        $result = [];
        $self->read(['email_id' => ['to', 'cc', 'bcc']]);

        foreach($self as $id => $history) {
            $recipients = [];

            foreach(['to', 'cc', 'bcc'] as $field) {
                if(empty($history['email_id'][$field])) {
                    continue;
                }

                foreach(explode(',', $history['email_id'][$field]) as $email) {
                    $email = trim($email);
                    if($email !== '') {
                        $recipients[] = $email;
                    }
                }
            }

            $result[$id] = implode(', ', array_values(array_unique($recipients)));
        }

        return $result;
    }
}
