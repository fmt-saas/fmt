<?php
/*
    This file is part of FMT SaaS Software <https://github.com/fmt-saas/fmt>
    Some Rights Reserved, FMT SRL, 2025-2026
    Licensed under GNU AGPL 3 license <http://www.gnu.org/licenses/>
*/

use communication\email\Email;
use documents\Document;
use realestate\property\Condominium;


[$params, $providers] = eQual::announce([
    'description'   => 'Fetch emails from IMAP for a given condominium and process them.',
    'params'        => [

        'id' => [
            'type'              => 'many2one',
            'description'       => "The ownership that the owner refers to.",
            'foreign_object'    => 'realestate\property\Condominium',
            'required'          => true
        ]

    ],
    'access'        => [
        'visibility' => 'protected'
    ],
    'response'      => [
        'content-type'  => 'application/pdf',
        'accept-origin' => '*'
    ],
    'providers'     => ['context'],
    'constants'     => ['L10N_TIMEZONE', 'L10N_LOCALE']
]);

/** @var \equal\php\Context $context */
$context = $providers['context'];

$result = [];


// Exemple : boucle sur les ACP configurées
/*
$condominium = Condominium::search(['imap_enabled' => true])
    ->read(['id', 'imap_host', 'imap_port', 'imap_user', 'imap_password'])
*/

$imap_host = 'imap.gmail.com';
$imap_port = '993';
$imap_encryption = 'ssl';
$imap_user = 'fmtsolutions.yb@gmail.com';
$imap_password = 'witnkvwhiwvgtgrk';


$mailbox = imap_open(
    '{' . $imap_host . ':' . $imap_port . '/imap/ssl}INBOX',
    $imap_user,
    $imap_password
);

if (!$mailbox) {
    trigger_error("IMAP connection failed for condo {$params['id']}: " . imap_last_error(), E_USER_ERROR);
    throw new Exception('imap_connection_failed', EQ_ERROR_INVALID_CONFIG);
}

$emails = imap_search($mailbox, 'UNSEEN');

foreach($emails ?? [] as $email_number) {
    $header = imap_headerinfo($mailbox, $email_number);
    $structure = imap_fetchstructure($mailbox, $email_number);
    $subject = imap_utf8($header->subject ?? '[no subject]');
    $body = imap_body($mailbox, $email_number);

    $email = Email::create([
            'condo_id'   => $params['id'],
            'direction'  => 'incoming',
            'date'       => $header->date ?? date('Y-m-d H:i:s'),
            'to'         => implode(', ', array_column($header->to ?? [], 'mailbox')) . '@' . ($header->to[0]->host ?? ''),
            'from'       => $header->from[0]->mailbox . '@' . $header->from[0]->host ?? null,
            'reply_to'   => $header->reply_to[0]->mailbox . '@' . $header->reply_to[0]->host ?? null,
            'subject'    => $subject,
            'body'       => $body,
            'status'     => 'pending'
        ])
        ->first(true);

    if(isset($structure->parts)) {
        foreach($structure->parts as $i => $part) {
            if($part->ifdparameters) {
                foreach($part->dparameters as $object) {
                    if(strtolower($object->attribute) === 'filename') {
                        $filename = $object->value;
                        $data = imap_fetchbody($mailbox, $email_number, $i + 1);

                        if($part->encoding == 3) {
                            $data = base64_decode($data);
                        }
                        elseif($part->encoding == 4) {
                            $data = quoted_printable_decode($data);
                        }

                        Document::create([
                            'email_id'     => $email['id'],
                            'name'         => $filename,
                            'data'         => $data
                        ]);
                    }
                }
            }
        }
    }

    // mark as read
    imap_setflag_full($mailbox, $email_number, "\\Seen");
}

imap_close($mailbox);


$context->httpResponse()
        ->body($result)
        ->send();
