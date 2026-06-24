<?php
/*
    Developed by Yesbabylon - https://yesbabylon.com
    (c) 2025-2026 Yesbabylon SA
    Licensed under the GNU AGPL v3 License - https://www.gnu.org/licenses/agpl-3.0.html
*/
namespace realestate\property;

class CondoLang extends \equal\orm\Model {

    public static function getColumns() {

        return [
            'name' => [
                'type'              => 'computed',
                'result_type'       => 'string',
                'relation'          => ['lang_id' => 'name'],
                'description'       => 'Display name of the language.',
                'store'             => true,
                'readonly'          => true
            ],

            'condo_id' => [
                'type'              => 'many2one',
                'foreign_object'    => 'realestate\property\Condominium',
                'description'       => 'Condominium using the language.',
                'required'          => true,
                'ondelete'          => 'cascade'
            ],

            'lang_id' => [
                'type'              => 'many2one',
                'foreign_object'    => 'core\Lang',
                'description'       => 'Language used by the condominium.',
                'required'          => true,
                'dependents'        => ['name', 'code'],
                'onupdate'          => 'onupdateLangId'
            ],

            'code' => [
                'type'              => 'computed',
                'result_type'       => 'string',
                'relation'          => ['lang_id' => 'code'],
                'description'       => 'ISO code of the linked language.',
                'store'             => true,
                'instant'           => true,
                'readonly'          => true
            ],

            'is_primary' => [
                'type'              => 'boolean',
                'description'       => 'Primary business language of the condominium.',
                'help'              => 'Only one language can be primary for a condominium. The primary language is also the translation fallback language.',
                'default'           => false,
                'onupdate'          => 'onupdateIsPrimary'
            ]
        ];
    }

    public function getIndexes(): array {
        return [
            ['condo_id'],
            ['lang_id']
        ];
    }

    public function getUnique() {
        return [
            ['condo_id', 'lang_id']
        ];
    }

    public static function getActions() {
        return [
            'sync_primary_lang' => [
                'description'   => 'Synchronize the primary condominium language.',
                'policies'      => [],
                'function'      => 'doSyncPrimaryLang'
            ]
        ];
    }

    protected static function oncreate($self) {
        $self->do('sync_primary_lang');
    }

    protected static function onupdateLangId($self) {
        $self->do('sync_primary_lang');
    }

    protected static function onupdateIsPrimary($self) {
        $self->do('sync_primary_lang');
    }

    public static function onchange($event, $values) {
        $result = [];

        if(array_key_exists('lang_id', $event)) {
            $lang_id = $event['lang_id'];
            if(is_array($lang_id)) {
                $lang_id = $lang_id['id'] ?? null;
            }

            $result['code'] = '';
            if($lang_id) {
                $lang = \core\Lang::id($lang_id)->read(['code'])->first();
                if($lang) {
                    $result['code'] = $lang['code'];
                }
            }
        }

        return $result;
    }

    protected static function doSyncPrimaryLang($self) {
        $self->read(['condo_id' => ['lang_id'], 'lang_id', 'is_primary']);

        foreach($self as $id => $condoLang) {
            if(!$condoLang['condo_id'] || !$condoLang['lang_id']) {
                continue;
            }

            $condo_id = $condoLang['condo_id']['id'];
            $condo_lang_id = $condoLang['condo_id']['lang_id'] ?? null;

            if($condoLang['is_primary']) {
                if((int) $condo_lang_id !== (int) $condoLang['lang_id']) {
                    Condominium::id($condo_id)->update(['lang_id' => $condoLang['lang_id']]);
                }

                self::search([
                        ['condo_id', '=', $condo_id],
                        ['id', '<>', $id],
                        ['is_primary', '=', true]
                    ])
                    ->update(['is_primary' => false]);

            }
        }
    }

    public static function candelete($self) {
        $self->read(['is_primary']);
        foreach($self as $condoLang) {
            if($condoLang['is_primary']) {
                return ['is_primary' => ['primary_required' => 'The primary condominium language cannot be removed.']];
            }
        }

        return parent::candelete($self);
    }
}
