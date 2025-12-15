<?php
/*
    Developed by Yesbabylon - https://yesbabylon.com
    (c) 2025-2026 Yesbabylon SA
    Licensed under the GNU AGPL v3 License - https://www.gnu.org/licenses/agpl-3.0.html
*/

namespace realestate\purchase\accounting\invoice;

use documents\DocumentType;
use documents\processing\DocumentProcess;
use equal\orm\Model;
use identity\User;

class PurchaseInvoiceImport extends Model {

    public static function getName() {
        return 'Purchase Invoice import';
    }

    public static function getDescription() {
        return 'Purchase Invoice Import is a virtual Entity for allowing import of grouped purchase invoices. These imports are meant to be removed upon successful processing.';
    }

    public static function getColumns() {

        return [

            'name' => [
                'type'              => 'string',
                'description'       => 'Display name of purchase invoice.',
            ],

            'data' => [
                'type'              => 'binary',
                'description'       => 'Raw binary data of the uploaded document',
                'help'              => 'This field is meant to be used for the subsequent document creation, and is emptied once the document creation is confirmed.',
                'onupdate'          => 'onupdateData'
            ]

        ];
    }

    /**
     * Handle data update (i.e. file upload).
     * This method is used to create the document based on received data, and start the processing.
     */
    protected static function onupdateData($self, $auth) {
        $self->read(['name', 'data']);
        $documentType = DocumentType::search(['code', '=', 'invoice'])->first();
        $user = User::id($auth->userId())->read(['employee_id'])->first();

        foreach($self as $id => $purchaseInvoiceImport) {
            // this will trigger the creation of the Document and the Document Processing, which should not interrupt the import even if it fails
            try {
                $documentProcess = DocumentProcess::create([
                        'name'                  => $purchaseInvoiceImport['name'],
                        'document_type_id'      => $documentType['id'],
                        'assigned_employee_id'  => $user['employee_id']
                    ])
                    ->update(['data' => $purchaseInvoiceImport['data']])
                    ->read(['document_id'])
                    ->first();
            }
            catch(\Exception $e) {
                // ignore (outputs are in logs)
            }

            // remove current object (pointless after successful import)
            self::id($id)->delete(true);
        }
    }

    /**
     * PurchaseInvoiceImport is used to upload and create a new Document.
     * We rely on the same strategy than regular Document upload, by receiving document meta from UI with onchange event.
     */
    public static function onchange($event, $values) {
        $result = [];

        if(isset($event['data']['name'])) {
            $result['name'] = $event['data']['name'];
        }

        return $result;
    }

}
