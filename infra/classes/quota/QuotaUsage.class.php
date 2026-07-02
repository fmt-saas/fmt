<?php

namespace infra\quota;

use equal\orm\Model;

class QuotaUsage extends Model {

    public static function getDescription(): string {
        return 'The current state of a quota.';
    }

    public static function getColumns(): array {
        return [

            'name' => [
                'type'              => 'string',
                'description'       => 'Name of the threshold.',
                'required'          => true
            ],

            'definition_id' => [
                'type'              => 'many2one',
                'foreign_object'    => 'infra\quota\QuotaDefinition',
                'description'       => 'The quota definition of the usage.',
                'required'          => true
            ],

            'code' => [
                'type'              => 'computed',
                'result_type'       => 'string',
                'usage'             => 'text/plain:128',
                'relation'          => ['definition_id' => 'code'],
                'description'       => 'Unique technical code of the usage.',
                'store'             => true,
                'instant'           => true
            ],

            'period_start' => [
                'type'              => 'datetime',
                'description'       => 'Start of period needed if the definition type is "period".'
            ],

            'period_end' => [
                'type'              => 'datetime',
                'description'       => 'End of period needed if the definition type is "period".'
            ],

            'value' => [
                'type'              => 'integer',
                'description'       => 'Current usage value of a defined quota.',
                'required'          => true
            ],

            'is_reached' => [
                'type'              => 'boolean',
                'description'       => 'Is the quota exceeded, in most cases the feature must be blocked.',
                'default'           => false
            ],

            'thresholds_ids' => [
                'type'              => 'one2many',
                'foreign_object'    => 'infra\quota\QuotaThreshold',
                'foreign_field'     => 'definition_id',
                'description'       => 'The thresholds that handle the is_reached value or alerts trigger.'
            ]

        ];
    }

    public function getUnique(): array {
        return [
            ['code']
        ];
    }

    public static function getActions(): array {
        return [

            'update-value' => [
                'description'   => 'Update the quota usage value using the definition inspect data controller.',
                'function'      => 'doUpdateValue'
            ],

            'check-thresholds' => [
                'description'   => 'Check the threshold and trigger action if defined threshold value reached.',
                'function'      => 'doCheckThreshold'
            ]

        ];
    }

    protected static function doUpdateValue($self): void {
        $self->read(['definition_id' => ['metric_definition_id' => ['collector']]]);
        foreach ($self as $id => $quota_usage) {
            $inspect_res = \eQual::run('get', $quota_usage['definition_id']['metric_definition_id']['collector']);

            self::id($id)->update(['value' => $inspect_res['value']]);
        }
    }

    protected static function doCheckThreshold($self): void {
        $self->read(['value', 'thresholds_ids' => ['value', 'max_value']]);
        foreach($self as $quota_usage) {
            foreach($quota_usage['thresholds_ids'] as $threshold) {
                if($quota_usage['value'] >= $threshold['value'] && (!$threshold['max_value'] || $quota_usage['value'] < $threshold['max_value'])) {
                    \eQual::run('do', $threshold['action']);
                }
            }
        }
    }
}

