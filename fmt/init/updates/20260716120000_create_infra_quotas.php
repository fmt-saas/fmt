<?php

use infra\metering\MetricDefinition;
use infra\quota\Quota;
use infra\quota\QuotaThreshold;

$currentWeekStart = strtotime('Monday this week', time());
$currentWeekEnd = strtotime('Sunday this week 23:59:59', time());

$propertyMainLotsCountMetricDefinition = MetricDefinition::create([
        'code'                      => 'property.main_lots.count',
        'name'                      => 'Nombre de lots principaux',
        'description'               => 'Nombre de lots principaux actifs dans l\'instance.',
        'category'                  => 'fmt',
        'unit'                      => 'count',
        'collector'                 => 'infra_metering_read-property-main-lots-count',
        'is_active'                 => true
    ], 'fr')
    ->first();

$propertyMainLotsCountQuota = Quota::create([
        'metric_definition_id'      => $propertyMainLotsCountMetricDefinition['id'],
        'code'                      => 'property.main_lots.count',
        'name'                      => 'Nombre de lots principaux',
        'quota_type'                => 'instant',
        'period_duration'           => 'week',
        'period_start'              => null,
        'period_end'                => null,
        'availability_controller'   => 'infra_quota_check-property-main-lots-count-availability',
        'value'                     => 0,
        'is_reached'                => false,
        'is_active'                 => false
    ], 'fr')
    ->first();

$propertyMainLotsCountQuotaThreshold = QuotaThreshold::create([
        'name'                      => 'Max - lots principaux',
        'quota_id'                  => $propertyMainLotsCountQuota['id'],
        'threshold_type'            => 'non_blocking',
        'value'                     => 5,
        'max_value'                 => null,
        'action'                    => 'infra_quota_handle-property-main-lots-count-reached'
    ], 'fr')
    ->first();

$propertyParkingsCountMetricDefinition = MetricDefinition::create([
        'code'                      => 'property.parkings.count',
        'name'                      => 'Nombre de garages/parkings',
        'description'               => 'Nombre de garages et parkings actifs dans l\'instance.',
        'category'                  => 'fmt',
        'unit'                      => 'count',
        'collector'                 => 'infra_metering_read-property-parkings-count',
        'is_active'                 => true
    ], 'fr')
    ->first();

$propertyParkingsCountQuota = Quota::create([
        'metric_definition_id'      => $propertyParkingsCountMetricDefinition['id'],
        'code'                      => 'property.parkings.count',
        'name'                      => 'Nombre de garages/parkings',
        'quota_type'                => 'instant',
        'period_duration'           => 'week',
        'period_start'              => null,
        'period_end'                => null,
        'availability_controller'   => 'infra_quota_check-property-parkings-count-availability',
        'value'                     => 0,
        'is_reached'                => false,
        'is_active'                 => false
    ], 'fr')
    ->first();

$propertyParkingsCountQuotaThreshold = QuotaThreshold::create([
        'name'                      => 'Max - garages/parkings',
        'quota_id'                  => $propertyParkingsCountQuota['id'],
        'threshold_type'            => 'non_blocking',
        'value'                     => 10,
        'max_value'                 => null,
        'action'                    => 'infra_quota_handle-property-parkings-count-reached'
    ], 'fr')
    ->first();

$edmsDocumentCountMetricDefinition = MetricDefinition::create([
        'code'                      => 'edms.document.count',
        'name'                      => 'Nombre de documents EDMS',
        'description'               => 'Nombre total de documents stockés dans l\'EDMS.',
        'category'                  => 'edms',
        'unit'                      => 'count',
        'collector'                 => 'infra_metering_read-edms-document-count',
        'is_active'                 => true
    ], 'fr')
    ->first();

$edmsDocumentCountQuota = Quota::create([
        'metric_definition_id'      => $edmsDocumentCountMetricDefinition['id'],
        'code'                      => 'edms.document.count',
        'name'                      => 'Nombre de documents EDMS',
        'quota_type'                => 'instant',
        'period_duration'           => 'week',
        'period_start'              => null,
        'period_end'                => null,
        'availability_controller'   => 'infra_quota_check-edms-document-count-availability',
        'value'                     => 0,
        'is_reached'                => false,
        'is_active'                 => false
    ], 'fr')
    ->first();

$edmsDocumentCountQuotaThreshold = QuotaThreshold::create([
        'name'                      => 'Max - documents EDMS',
        'quota_id'                  => $edmsDocumentCountQuota['id'],
        'threshold_type'            => 'non_blocking',
        'value'                     => 100,
        'max_value'                 => null,
        'action'                    => 'infra_quota_handle-edms-document-count-reached'
    ], 'fr')
    ->first();

$edmsStorageSizeMetricDefinition = MetricDefinition::create([
        'code'                      => 'edms.storage.size',
        'name'                      => 'Volume de stockage EDMS',
        'description'               => 'Volume total consommé par les documents stockés dans l\'EDMS.',
        'category'                  => 'edms',
        'unit'                      => 'bytes',
        'collector'                 => 'infra_metering_read-edms-storage-size',
        'is_active'                 => true
    ], 'fr')
    ->first();

$edmsStorageSizeQuota = Quota::create([
        'metric_definition_id'      => $edmsStorageSizeMetricDefinition['id'],
        'code'                      => 'edms.storage.size',
        'name'                      => 'Volume de stockage EDMS',
        'quota_type'                => 'instant',
        'period_duration'           => 'week',
        'period_start'              => null,
        'period_end'                => null,
        'availability_controller'   => 'infra_quota_check-edms-storage-size-availability',
        'value'                     => 0,
        'is_reached'                => false,
        'is_active'                 => false
    ], 'fr')
    ->first();

$edmsStorageSizeQuotaThreshold = QuotaThreshold::create([
        'name'                      => 'Max - stockage EDMS',
        'quota_id'                  => $edmsStorageSizeQuota['id'],
        'threshold_type'            => 'non_blocking',
        'value'                     => 209715200,
        'max_value'                 => null,
        'action'                    => 'infra_quota_handle-edms-storage-size-reached'
    ], 'fr')
    ->first();

$googleDocaiCallsCountMetricDefinition = MetricDefinition::create([
        'code'                      => 'google.docai.calls.count',
        'name'                      => 'Appels Google Document AI',
        'description'               => 'Nombre d\'appels effectués vers Google Document AI.',
        'category'                  => 'google_doc_ai',
        'unit'                      => 'calls',
        'collector'                 => 'infra_metering_read-google-docai-calls-count',
        'is_active'                 => true
    ], 'fr')
    ->first();

$googleDocaiCallsCountQuota = Quota::create([
        'metric_definition_id'      => $googleDocaiCallsCountMetricDefinition['id'],
        'code'                      => 'google.docai.calls.count',
        'name'                      => 'Appels Google Document AI',
        'quota_type'                => 'period',
        'period_duration'           => 'week',
        'period_start'              => $currentWeekStart,
        'period_end'                => $currentWeekEnd,
        'value'                     => 0,
        'is_reached'                => false,
        'is_active'                 => false
    ], 'fr')
    ->first();

$googleDocaiCallsCountQuotaThreshold = QuotaThreshold::create([
        'name'                      => 'Max semaine - appels Google Document AI',
        'quota_id'                  => $googleDocaiCallsCountQuota['id'],
        'threshold_type'            => 'non_blocking',
        'value'                     => 100,
        'max_value'                 => null,
        'action'                    => 'infra_quota_handle-google-docai-calls-count-reached'
    ], 'fr')
    ->first();

$authUsersCountMetricDefinition = MetricDefinition::create([
        'code'                      => 'auth.users.count',
        'name'                      => 'Nombre de comptes utilisateurs',
        'description'               => 'Nombre de comptes utilisateurs actifs sur l\'instance.',
        'category'                  => 'auth',
        'unit'                      => 'count',
        'collector'                 => 'infra_metering_read-instance-users-count',
        'is_active'                 => true
    ], 'fr')
    ->first();

$authUsersCountQuota = Quota::create([
        'metric_definition_id'      => $authUsersCountMetricDefinition['id'],
        'code'                      => 'auth.users.count',
        'name'                      => 'Nombre de comptes utilisateurs',
        'quota_type'                => 'instant',
        'period_duration'           => 'week',
        'period_start'              => null,
        'period_end'                => null,
        'availability_controller'   => 'infra_quota_check-auth-users-count-availability',
        'value'                     => 0,
        'is_reached'                => false,
        'is_active'                 => false
    ], 'fr')
    ->first();

$authUsersCountQuotaThreshold = QuotaThreshold::create([
        'name'                      => 'Max - comptes utilisateurs',
        'quota_id'                  => $authUsersCountQuota['id'],
        'threshold_type'            => 'non_blocking',
        'value'                     => 5,
        'max_value'                 => null,
        'action'                    => 'infra_quota_handle-instance-users-count-reached'
    ], 'fr')
    ->first();

$emailOutboundCountMetricDefinition = MetricDefinition::create([
        'code'                      => 'email.outbound.count',
        'name'                      => 'Emails envoyés',
        'description'               => 'Nombre d\'emails envoyés depuis l\'instance sur la période considérée.',
        'category'                  => 'mail',
        'unit'                      => 'count',
        'collector'                 => 'infra_metering_read-mail-outbound-count',
        'is_active'                 => true
    ], 'fr')
    ->first();

$emailOutboundCountQuota = Quota::create([
        'metric_definition_id'      => $emailOutboundCountMetricDefinition['id'],
        'code'                      => 'email.outbound.count',
        'name'                      => 'Emails envoyés',
        'quota_type'                => 'period',
        'period_duration'           => 'week',
        'period_start'              => $currentWeekStart,
        'period_end'                => $currentWeekEnd,
        'value'                     => 0,
        'is_reached'                => false,
        'is_active'                 => false
    ], 'fr')
    ->first();

$emailOutboundCountQuotaThreshold = QuotaThreshold::create([
        'name'                      => 'Max semaine - emails envoyés',
        'quota_id'                  => $emailOutboundCountQuota['id'],
        'threshold_type'            => 'non_blocking',
        'value'                     => 100,
        'max_value'                 => null,
        'action'                    => 'infra_quota_handle-mail-outbound-count-reached'
    ], 'fr')
    ->first();

$dbStorageSizeMetricDefinition = MetricDefinition::create([
        'code'                      => 'db.storage.size',
        'name'                      => 'Taille de la base de données',
        'description'               => 'Taille totale consommée par la base de données de l\'instance.',
        'category'                  => 'database',
        'unit'                      => 'bytes',
        'collector'                 => 'infra_metering_read-db-storage-size',
        'is_active'                 => true
    ], 'fr')
    ->first();

$dbStorageSizeQuota = Quota::create([
        'metric_definition_id'      => $dbStorageSizeMetricDefinition['id'],
        'code'                      => 'db.storage.size',
        'name'                      => 'Taille de la base de données',
        'quota_type'                => 'instant',
        'period_duration'           => 'week',
        'period_start'              => null,
        'period_end'                => null,
        'value'                     => 0,
        'is_reached'                => false,
        'is_active'                 => false
    ], 'fr')
    ->first();

$dbStorageSizeQuotaThreshold = QuotaThreshold::create([
        'name'                      => 'Max - taille de la base de données',
        'quota_id'                  => $dbStorageSizeQuota['id'],
        'threshold_type'            => 'non_blocking',
        'value'                     => 524288000,
        'max_value'                 => null,
        'action'                    => 'infra_quota_handle-db-storage-size-reached'
    ], 'fr')
    ->first();
