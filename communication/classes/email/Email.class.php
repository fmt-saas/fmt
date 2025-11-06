<?php
/*
    Developed by Yesbabylon - https://yesbabylon.com
    (c) 2025-2026 Yesbabylon SA
    Licensed under the GNU AGPL v3 License - https://www.gnu.org/licenses/agpl-3.0.html
*/
namespace communication\email;

use equal\orm\Model;


class Email extends Model {


    public static function getColumns() {
        return [
            'condo_id' => [
                'type'              => 'many2one',
                'description'       => "The condominium the email relates to.",
                'foreign_object'    => 'realestate\property\Condominium'
            ],

            'name' => [
                'type'              => 'computed',
                'result_type'       => 'string',
                'relation'          => ['subject']
            ],

            'mailbox_id' => [
                'type'              => 'many2one',
                'description'       => "The mailbox the email relates to, if any.",
                'foreign_object'    => 'communication\email\Mailbox'
            ],

            'message_id' => [
                'type'              => 'string',
                'description'       => "Unique string identifier of the message as per RFC 5322.",
                'unique'            => true
            ],

            'ownership_id' => [
                'type'              => 'many2one',
                'description'       => "The ownership that the owner refers to.",
                'foreign_object'    => 'realestate\ownership\Ownership',
                'domain'            => ['condo_id', '=', 'object.condo_id']
            ],

            'suppliership_id' => [
                'type'              => 'many2one',
                'description'       => "The suppliership the email relates to, if any.",
                'foreign_object'    => 'purchase\supplier\Suppliership',
                'domain'            => ['condo_id', '=', 'object.condo_id']
            ],

            'documents_ids' => [
                'type'              => 'one2many',
                'foreign_field'     => 'email_id',
                'foreign_object'    => 'documents\Document',
                'description'       => 'Documents attached to the email.'
            ],

            'case_file_id' => [
                'type'              => 'many2one',
                'foreign_object'    => 'tracking\CaseFile',
                'description'       => 'Optional link to the related case file (incident, quote, etc.).',
            ],

            'date' => [
                'type'              => 'datetime',
                'description'       => 'Date and time of the email message.',
                'default'           => function() { return time(); }
            ],

            'direction' => [
                'type'              => 'string',
                'selection'         => ['outgoing', 'incoming'],
                'default'           => 'outgoing',
                'description'       => 'Direction of the email message.',
            ],

            'thread_hash' => [
                'type'              => 'string',
                'description'       => 'Hash of the cleaned subject to group conversations.',
                'help'              => 'The hash is base on the email subject, discarding any `Re` and `Fwd`',
                'function'          => 'calcThreadHash',
                'store'             => true
            ],

            'to' => [
                'type'              => 'string',
                'usage'             => 'email',
                'required'          => true
            ],

            'from' => [
                'type'              => 'string',
                'usage'             => 'email'
            ],

            'reply_to' => [
                'type'              => 'string',
                'usage'             => 'email'
            ],

            'cc' => [
                'type'              => 'string',
                'description'       => 'Comma separated list of carbon-copy recipients.'
            ],

            'bcc' => [
                'type'              => 'string',
                'description'       => 'Comma separated list of blind carbon-copy recipients.'
            ],

            'subject' => [
                'type'              => 'string',
                'required'          => true,
                'dependents'        => ['name', 'thread_hash']
            ],

            'body' => [
                'type'              => 'string',
                'usage'             => 'text/html',
                'required'          => true
            ],

            'object_class' => [
                'type'              => 'string',
                'description'       => 'Class of the object object_id points to.'
            ],

            'object_id' => [
                'type'              => 'integer',
                'description'       => 'Identifier of the object the email originates from.'
            ],

            'has_error' => [
                'type'              => 'computed',
                'result_type'       => 'boolean',
                'description'       => 'SMTP response status code.',
                'function'          => 'calcHasError',
                'store'             => true,
                'visible'           => [['direction', '=', 'outgoing'], ['status', '<>', 'pending']]
            ],

            'response_status' => [
                'type'              => 'integer',
                'description'       => 'SMTP response status code.',
                'visible'           => [['direction', '=', 'outgoing'], ['status', '<>', 'pending']],
                'dependents'        => ['has_error']
            ],

            'response' => [
                'type'              => 'string',
                'description'       => 'SMTP response returned at sending.',
                'default'           => '',
                'visible'           => [['direction', '=', 'outgoing'], ['status', '<>', 'pending']]
            ],

            'status' => [
                'type'              => 'string',
                'selection'         => [
                    'pending',
                    'processed'
                ],
                'default'           => 'pending',
                'description'       => 'Sending status of the mail.',
                'visible'           => [['direction', '=', 'outgoing']]
            ]

        ];
    }

    protected static function calcHasError($self) {
        $result = [];
        $self->read(['response_status']);
        foreach($self as $id => $email) {
            if($email['response_status']) {
                $result[$id] = ($email['response_status'] !== 250);
            }
        }
        return $result;
    }

    protected static function calcThreadHash($self) {
        $result = [];
        $self->read(['subject']);
        foreach($self as $id => $email) {
            if(!$email['subject']) {
                continue;
            }
            $cleaned_subject = preg_replace('/^((re|fwd)\s*:\s*)+/i', '', $email['subject']);
            if(strlen($cleaned_subject) > 0) {
                $result[$id] = hash('sha1', strtolower(trim($cleaned_subject)));
            }
        }
        return $result;
    }


}
