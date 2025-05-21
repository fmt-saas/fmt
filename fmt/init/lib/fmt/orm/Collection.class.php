<?php
/*
    This file is part of FMT SaaS Software <https://github.com/fmt-saas/fmt>
    Some Rights Reserved, FMT SRL, 2025-2026
    Licensed under GNU AGPL 3 license <http://www.gnu.org/licenses/>
*/
namespace fmt\orm;

use fmt\setting\Setting;
use equal\orm\Domain;

class Collection extends \equal\orm\Collection {


    /*
    public function __construct($class, $objectManager, $accessController, $authenticationManager, $dataAdapterProvider, $logger) {
        parent::__construct($class, $objectManager, $accessController, $authenticationManager, $dataAdapterProvider, $logger);
    }
    */

    /**
     * Feed the Collection with the IDs of the objects (of current class) that comply with the given domain.
     * If no domain is given and parameter limit is left to 0, all objects are taken in account (Warning: reading such Collection might consume a large amount of memory)
     *
     * This methods adds a constraint on the condo_id field, if the current user has specified one (through settings).
     * #memo - further tests will be performed in parent class, through an overload of the AccessController.
     */
    public function search(array $domain=[], array $params=[], $lang=null) {

        // retrieve current user id
        $user_id = $this->am->userId();
        $schema = $this->model->getSchema();

        if(isset($schema['condo_id'])) {
            $condo_id = Setting::get_value('fmt', 'organization', 'user.condo_id', null, ['user_id' => $user_id]);
            if($condo_id) {
                // sanitize and validate domain
                if(!empty($domain)) {
                    $domain = Domain::normalize($domain);
                    if(!Domain::validate($domain, $schema)) {
                        throw new \Exception(serialize(['invalid_domain' => Domain::toString($domain)]), EQ_ERROR_INVALID_PARAM);
                    }
                }
                // add a condition to the domain to limit to currently selected Condominium
                $domain = Domain::conditionAdd($domain, ['condo_id', '=', $condo_id]);
            }
        }

        parent::search($domain, $params, $lang);

        return $this;
    }

    public function create(array $values=null, $lang=null) {

        if(\eQual::constant('FMT_INSTANCE_TYPE') === 'agency') {
            // #todo - adapt according to final logic
            static $map_classes = [
                'protected' => [
                    'core\User'                      => true,
                    'identity\Identity'              => true,
                    'purchase\supplier\Supplier'     => true,
                    'documents\DocumentType'         => true
                ]
            ];

            static $root_class = null;

            if(!$root_class) {
                $root_class = $this->orm->getObjectRootClass($this->class);
            }

            if(isset($map_classes['private'][$root_class])) {
                trigger_error("APP::Creation of private object {$this->class} forbidden for agency instance.", EQ_REPORT_WARNING);
                throw new \Exception('private_entity', EQ_ERROR_INVALID_PARAM);
            }
        }

        parent::create($values, $lang);

        return $this;
    }

}
