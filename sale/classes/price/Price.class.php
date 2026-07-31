<?php
/*
    Developed by Yesbabylon - https://yesbabylon.com
    (c) 2025-2026 Yesbabylon SA
    Licensed under the GNU AGPL v3 License - https://www.gnu.org/licenses/agpl-3.0.html
*/

namespace sale\price;

use equal\orm\Model;
use finance\accounting\AccountingRule;

class Price extends Model {

    public static function getName(): string {
        return 'Price';
    }

    public static function getDescription(): string {
        return 'A price is an amount of money that corresponds to the sale price of a product or service.'
            .' It is described by an amount, a vat rate, an accounting rule and is part of a price list.';
    }

    public static function getColumns(): array {
        return [

            'name' => [
                'type'              => 'computed',
                'function'          => 'calcName',
                'result_type'       => 'string',
                'store'             => true,
                'description'       => "The display name of the price."
            ],

            'condo_id' => [
                'type'              => 'computed',
                'result_type'       => 'many2one',
                'foreign_object'    => 'realestate\property\Condominium',
                'relation'          => ['price_list_id' => 'condo_id'],
                'description'       => "The condominium the tenancy relates to.",
                'help'              => "If set, relates to the specific condominium the price applies to.",
                'store'             => true,
                'instant'           => true
            ],

            'price' => [
                'type'              => 'float',
                'usage'             => 'amount/money:4',
                'description'       => "Tax excluded price.",
                'required'          => true,
                'dependents'        => ['price_vat']
            ],

            'price_vat' => [
                'type'              => 'computed',
                'result_type'       => 'float',
                'function'          => 'calcPriceVat',
                'usage'             => 'amount/money:2',
                'description'       => "Tax included price. This field is used to allow encoding prices VAT incl.",
                'store'             => true
            ],

            'vat_rate' => [
                'type'              => 'computed',
                'result_type'       => 'float',
                'usage'             => 'amount/rate',
                'relation'          => ['accounting_rule_id' => ['vat_rule_id' => 'rate']],
                'description'       => "VAT rate applied on the price (from accounting rule).",
                'store'             => true,
                'readonly'          => true
            ],

            'price_list_id' => [
                'type'              => 'many2one',
                'foreign_object'    => 'sale\price\PriceList',
                'description'       => "The Price List the price belongs to.",
                'required'          => true,
                'ondelete'          => 'cascade',
                'dependents'        => ['name', 'condo_id']
            ],

            'is_active' => [
                'type'              => 'computed',
                'result_type'       => 'boolean',
                'relation'          => ['price_list_id' => 'is_active'],
                'store'             => true,
                'instant'           => true,
                'description'       => "Is the price currently applicable?"
            ],

            'accounting_rule_id' => [
                'type'              => 'many2one',
                'foreign_object'    => 'finance\accounting\AccountingRule',
                'description'       => "Selling accounting rule. If set, overrides the rule of the product this price is assigned to.",
                'dependents'        => ['vat_rate', 'price_vat']
            ],

            'product_id' => [
                'type'              => 'many2one',
                'foreign_object'    => 'sale\catalog\Product',
                'description'       => "The Product (sku) the price applies to.",
                'required'          => true,
                'dependents'        => ['name']
            ]

        ];
    }

    public function getUnique(): array {
        return [
            ['condo_id', 'product_id', 'price_list_id']
        ];
    }

    public static function calcName($self): array {
        $result = [];
        $self->read([
            'product_id'    => ['sku'],
            'price_list_id' => ['name']
        ]);
        foreach($self as $id => $product) {
            if(isset($product['product_id'], $product['price_list_id'])) {
                $result[$id] = "{$product['product_id']['sku']} [{$product['product_id']['id']}] - {$product['price_list_id']['name']}";
            }
        }

        return $result;
    }

    public static function calcPriceVat($self): array {
        $result = [];
        $self->read(['price', 'vat_rate']);
        foreach($self as $id => $price) {
            $result[$id] = self::computePriceVatIncluded($price['price'], $price['vat_rate']);
        }

        return $result;
    }

    public static function onchange($event, $values): array {
        $result = [];

        if(isset($event['accounting_rule_id'])) {
            $rule = AccountingRule::id($event['accounting_rule_id'])
                ->read(['vat_rule_id' => 'rate'])
                ->first();

            $result['vat_rate'] = $rule['vat_rule_id']['rate'];
        }

        if(isset($event['price'])) {
            $result['price_vat'] = self::computePriceVatIncluded($event['price'], $values['vat_rate'] ?? 0.0);
        }
        elseif(isset($event['price_vat'])) {
            $result['price'] = self::computePriceVatExcluded($event['price_vat'], $values['vat_rate'] ?? 0.0);
        }

        return $result;
    }

    public static function computePriceVatIncluded($price, $vat_rate): float {
        return round($price * (1.0 + $vat_rate), 2);
    }

    public static function computePriceVatExcluded($price_vat, $vat_rate): float {
        return $price_vat / (1.0 + $vat_rate);
    }
}
