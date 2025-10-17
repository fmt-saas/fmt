<?php
/*
    Developed by Yesbabylon – https://yesbabylon.com
    (c) 2025–2026 Yesbabylon SA
    Licensed under the GNU AGPL v3 License – https://www.gnu.org/licenses/agpl-3.0.html
*/
namespace documents;

use equal\data\DataGenerator;
use equal\orm\Model;

class DocumentSubtype extends Model {

    public static function constants() {
        return ['FMT_INSTANCE_TYPE'];
    }

    public static function getColumns() {
        return [

            'name' => [
                'type'              => 'string',
                'description'       => 'Name of the document Subtype.',
                'required'          => true
            ],

            'uuid' => [
                'type'              => 'string',
                'usage'             => 'text/plain:36',
                'unique'            => true,
                'description'       => 'Unique supplier identifier provided by GLOBAL instance.'
            ],

            'code' => [
                'type'              => 'string',
                'description'       => 'Unique code identifier of the document Subtype.',
                'required'          => true,
                'unique'            => true
            ],

            'folder_code' => [
                'type'              => 'string',
                'description'       => 'Code of the Folder node a document by this type must be assigned to.'
            ],

            'description' => [
                'type'              => 'string',
                'usage'             => 'text/plain.short',
                'description'       => 'Description of the purpose and usage of the tag.'
            ],

            'document_type_id' => [
                'type'              => 'many2one',
                'foreign_object'    => 'documents\DocumentType',
                'description'       => 'Parent documents type.'
            ],

            'recording_rules_ids' => [
                'type'              => 'one2many',
                'foreign_object'    => 'documents\recording\RecordingRule',
                'foreign_field'     => 'document_subtype_id',
                'description'       => 'Rules matching the document subtype.'
            ],

            'labeling_rules_ids' => [
                'type'              => 'one2many',
                'foreign_object'    => 'documents\labeling\LabelingRule',
                'foreign_field'     => 'document_subtype_id',
                'description'       => 'Rules matching the document subtype.'
            ],

            'documents_ids' => [
                'type'              => 'one2many',
                'foreign_object'    => 'documents\Document',
                'foreign_field'     => 'document_subtype_id',
                'description'       => 'Documents matching the document subtype.'
            ]

        ];
    }

    /**
     * This is a "private class": upon creation, assign a unique UUID if on GLOBAL instance
     */
    protected static function oncreate($self, $orm) {
        foreach($self as $id => $object) {
            if(constant('FMT_INSTANCE_TYPE') === 'global') {
                do {
                    $uuid = DataGenerator::uuid();
                    $existing = $orm->search(static::class, ['uuid', '=', $uuid]);
                } while( $existing > 0 && count($existing) > 0 );

                self::id($id)->update(['uuid' => $uuid]);
            }
        }
    }
}


/*
// example for `invoice`
{
  "$schema": "https://json-schema.org/draft/2020-12/schema",
  "$id": "https://example.org/schemas/invoice.schema.json",
  "title": "Invoice Schema",
  "description": "Simplified invoice format compatible with UBL, inspired by schema.org/Invoice",
  "type": "object",
  "required": ["invoice_number", "issue_date", "currency", "supplier", "customer", "lines", "totals"],
  "properties": {
    "invoice_number": {
      "type": "string",
      "description": "Unique identifier for the invoice"
    },
    "issue_date": {
      "type": "string",
      "format": "date",
      "description": "Date the invoice was issued"
    },
    "due_date": {
      "type": ["string", "null"],
      "format": "date",
      "description": "Date by which the payment is due"
    },
    "currency": {
      "type": "string",
      "description": "Currency code in ISO 4217 format",
      "examples": ["EUR", "USD"]
    },

    "supplier": {
      "type": "object",
      "required": ["name", "street", "vat"],
      "properties": {
        "name": { "type": "string" },
        "street": { "type": "string" },
        "vat": { "type": "string" }
      }
    },

    "customer": {
      "type": "object",
      "required": ["name", "street"],
      "properties": {
        "name": { "type": "string" },
        "street": { "type": "string" }
      }
    },

    "customer_reference": {
      "type": ["string", "null"],
      "description": "Reference provided by the customer (e.g. PO number)"
    },

    "billing_period": {
      "type": "object",
      "required": ["start", "end"],
      "properties": {
        "start": { "type": ["string", "null"], "format": "date" },
        "end": { "type": ["string", "null"], "format": "date" }
      }
    },

    "payment_reference": {
      "type": ["string", "null"],
      "description": "Reference to use when making payment"
    },

    "payment_account": {
      "type": ["string", "null"],
      "description": "Bank account or IBAN to pay to"
    },

    "payment_account_name": {
      "type": ["string", "null"],
      "description": "Name associated with the payment account"
    },

    "payment_type": {
      "type": ["string", "null"],
      "description": "Type of payment expected (e.g. SEPA, Transfer)"
    },

    "auto_debit": {
      "type": "boolean",
      "description": "Indicates whether the invoice will be debited automatically"
    },

    "lines": {
      "type": "array",
      "items": {
        "type": "object",
        "required": ["id", "description", "amount", "unit_price"],
        "properties": {
          "id": { "type": "string" },
          "description": { "type": "string" },
          "amount": { "type": "number" },
          "unit_price": { "type": "number" }
        }
      }
    },

    "totals": {
      "type": "object",
      "required": ["subtotal", "total_excl_tax", "total_incl_tax", "payable_amount"],
      "properties": {
        "subtotal": { "type": "number" },
        "total_excl_tax": { "type": "number" },
        "total_incl_tax": { "type": "number" },
        "payable_amount": { "type": "number" }
      }
    }
  }
}

 */