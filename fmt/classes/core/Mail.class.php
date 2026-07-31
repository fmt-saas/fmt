<?php
/*
    Developed by Yesbabylon - https://yesbabylon.com
    (c) 2025-2026 Yesbabylon SA
    Licensed under the GNU AGPL v3 License - https://www.gnu.org/licenses/agpl-3.0.html
*/

namespace fmt\core;

use communication\email\Email;
use equal\email\Email as EmailMessage;
use equal\services\Container;
use infra\metering\MeteringRecord;
use infra\metering\MetricDefinition;

class Mail extends \core\Mail {

    public static function constants() {
        return ['FMT_EMAIL_DOMAIN_FILTER_ENABLED', 'FMT_EMAIL_DOMAIN_ALLOWED'];
    }

    public static function getColumns() {
        return [

            'status' => [
                'type'              => 'string',
                'selection'         => [
                    'pending',
                    'failing',
                    'sent'
                ],
                'default'           => 'pending',
                'description'       => 'Sending status of the mail.',
                'onupdate'          => 'onupdateStatus'
            ]

        ];
    }

    protected static function onupdateStatus($self) {
        $self->read(['status']);
        foreach($self as $mail) {
            if($mail['status'] === 'sent') {
                $metric_def = MetricDefinition::search(['code', '=', 'email.outbound.count'])
                    ->read(['id'])
                    ->first();

                if($metric_def) {
                    MeteringRecord::create([
                        'metric_definition_id' => $metric_def['id']
                    ]);
                }
                else {
                    trigger_error('APP::Unable to retrieve metering record for email counter, missing metric definition "email.outbound.count".', EQ_REPORT_WARNING);
                }
            }
        }
    }

    public static function send(EmailMessage $email, string $object_class = '', int $object_id = 0): int {
        // instant email sending is disabled
        return 0;
    }

    public static function queue(EmailMessage $email, string $object_class = '', int $object_id = 0, int $mailbox_id = 0): int {

        if(constant('FMT_EMAIL_DOMAIN_FILTER_ENABLED')) {
            $email_address = $email->to;
            $domain = strtolower(substr(strrchr($email_address, '@'), 1));

            $allowed_domains = array_map(
                static fn(string $domain): string => strtolower(trim($domain)),
                explode(',', constant('FMT_EMAIL_DOMAIN_ALLOWED'))
            );

            if(!in_array($domain, $allowed_domains, true)) {
                return 0;
            }
        }

        $email = self::createMail($email, $object_class, $object_id);
        $email_id = $email['id'];

        Email::id($email_id)->update(['mailbox_id' => $mailbox_id]);

        // #todo store this as setting in config.json
        $monitor = true;

        if($monitor) {
            // schedule email sending status check

            $container = Container::getInstance();

            /* @var \equal\cron\Scheduler $cron */
            $cron = $container->get(['cron']);

            $ten_minutes = 60 * 10;

            $cron->schedule(
                "monitoring.check_email.$email_id",
                time() + $ten_minutes,
                'fmt_monitoring_check-email',
                ['id' => $email_id]
            );
        }

        return $email_id;
    }
}
