<?php
/*
    Developed by Yesbabylon - https://yesbabylon.com
    (c) 2025-2026 Yesbabylon SA
    Licensed under the GNU AGPL v3 License - https://www.gnu.org/licenses/agpl-3.0.html
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
            // #memo #config #sync - sync between controllers
            $map_entities = [
                'identity\Identity'                     => 'protected',
                'identity\User'                         => 'protected',
                'purchase\supplier\Supplier'            => 'protected',
                'purchase\supplier\SupplierType'        => 'private',
                'finance\bank\Bank'                     => 'protected',
                'realestate\property\NotaryOffice'      => 'protected',
                'realestate\management\ManagingAgent'   => 'protected',
                'realestate\property\Condominium'       => 'protected',
                'documents\DocumentType'                => 'private',
                'documents\DocumentSubtype'             => 'private'
            ];

            // #todo - what should we do for root classes (from which others inherit) ?
            if(isset($map_entities[$this->class]) && $map_entities[$this->class] === 'private') {
                $user_id = $this->am->userId();
                if($user_id != EQ_ROOT_USER_ID) {
                    trigger_error("APP::Creation of private object {$this->class} forbidden for agency instance.", EQ_REPORT_WARNING);
                    throw new \Exception('private_entity', EQ_ERROR_INVALID_PARAM);
                }
            }
        }

        parent::create($values, $lang);

        return $this;
    }

}
