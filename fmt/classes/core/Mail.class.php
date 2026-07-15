<?php
/*
    Developed by Yesbabylon - https://yesbabylon.com
    (c) 2025-2026 Yesbabylon SA
    Licensed under the GNU AGPL v3 License - https://www.gnu.org/licenses/agpl-3.0.html
*/

namespace fmt\core;

use infra\metering\MeteringRecord;
use infra\metering\MetricDefinition;

class Mail extends \core\Mail {

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
}
