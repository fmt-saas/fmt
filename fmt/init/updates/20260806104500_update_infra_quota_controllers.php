<?php

use infra\metering\MetricDefinition;
use infra\quota\Quota;
use infra\quota\QuotaThreshold;

$metric_collectors = [
    'property.main_lots.count'   => 'infra_metering_read-property-main-lots-count',
    'property.parkings.count'    => 'infra_metering_read-property-parkings-count',
    'edms.document.count'        => 'infra_metering_read-edms-document-count',
    'edms.storage.size'          => 'infra_metering_read-edms-storage-size',
    'google.docai.calls.count'   => 'infra_metering_read-google-docai-calls-count',
    'auth.users.count'           => 'infra_metering_read-instance-users-count',
    'email.outbound.count'       => 'infra_metering_read-mail-outbound-count',
    'db.storage.size'            => 'infra_metering_read-db-storage-size'
];

$quota_availability_controllers = [
    'property.main_lots.count'   => 'infra_quota_check-property-main-lots-count-availability',
    'property.parkings.count'    => 'infra_quota_check-property-parkings-count-availability',
    'edms.document.count'        => 'infra_quota_check-edms-document-count-availability',
    'edms.storage.size'          => 'infra_quota_check-edms-storage-size-availability',
    'google.docai.calls.count'   => null,
    'auth.users.count'           => 'infra_quota_check-auth-users-count-availability',
    'email.outbound.count'       => null,
    'db.storage.size'            => null
];

$quota_threshold_actions = [
    'property.main_lots.count'   => 'infra_quota_handle-property-main-lots-count-reached',
    'property.parkings.count'    => 'infra_quota_handle-property-parkings-count-reached',
    'edms.document.count'        => 'infra_quota_handle-edms-document-count-reached',
    'edms.storage.size'          => 'infra_quota_handle-edms-storage-size-reached',
    'google.docai.calls.count'   => 'infra_quota_handle-google-docai-calls-count-reached',
    'auth.users.count'           => 'infra_quota_handle-instance-users-count-reached',
    'email.outbound.count'       => 'infra_quota_handle-mail-outbound-count-reached',
    'db.storage.size'            => 'infra_quota_handle-db-storage-size-reached'
];

foreach($metric_collectors as $code => $collector) {
    MetricDefinition::search(['code', '=', $code])
        ->update(['collector' => $collector]);
}

foreach($quota_availability_controllers as $code => $availability_controller) {
    Quota::search(['code', '=', $code])
        ->update(['availability_controller' => $availability_controller]);
}

foreach($quota_threshold_actions as $code => $action) {
    $quotas_ids = Quota::search(['code', '=', $code])->ids();

    if(!count($quotas_ids)) {
        continue;
    }

    QuotaThreshold::search(['quota_id', 'in', $quotas_ids])
        ->update(['action' => $action]);
}
