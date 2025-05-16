<?php
/*
    This file is part of FMT SaaS Software <https://github.com/fmt-saas/fmt>
    Some Rights Reserved, FMT SRL, 2025-2026
    Licensed under GNU AGPL 3 license <http://www.gnu.org/licenses/>
*/
namespace documents;

use equal\orm\Model;

class DocumentSubtype extends Model {

    public static function getColumns() {
        return [

            'name' => [
                'type'              => 'string',
                'description'       => 'Name of the document Subtype.',
                'required'          => true
            ],

            'code' => [
                'type'              => 'string',
                'description'       => 'Unique code identifier of the document Subtype.',
                'required'          => true,
                'unique'            => true
            ],

            'folder_code' => [
                'type'              => 'string',
                'description'       => 'Code of the Folder node a document by this type must be assigned to.',
                'required'          => true
            ],

            'description' => [
                'type'              => 'string',
                'usage'             => 'text/plain.short',
                'description'       => 'Description of the purpose and usage of the tag.'
            ],

            'document_type_id' => [
                'type'              => 'one2many',
                'foreign_object'    => 'documents\DocumentType',
                'foreign_field'     => 'document_type_id',
                'description'       => 'Parent documents type.'
            ],

            'recording_rules_ids' => [
                'type'              => 'one2many',
                'foreign_object'    => 'documents\recording\RecordingRule',
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