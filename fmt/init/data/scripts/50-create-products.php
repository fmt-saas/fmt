<?php
/*
    Developed by Yesbabylon - https://yesbabylon.com
    (c) 2025-2026 Yesbabylon SA
    Licensed under the GNU AGPL v3 License - https://www.gnu.org/licenses/agpl-3.0.html
*/

use fmt\setting\Setting;
use sale\catalog\Product;
use sale\catalog\ProductModel;
use sale\price\Price;
use sale\price\PriceList;
use sale\price\PriceListCategory;


$product_model = ProductModel::create([
        'name'      => 'Frais de dossier mutation',
        'family_id' => 1,
        'can_buy'   => false,
        'can_sell'  => true
    ])
    ->first();

Product::create([
        'product_model_id'  => $product_model['id'],
        'label'             => "Frais de dossier mutation",
        'sku'               => "OWNERSHIP_TRANSFER_FEE",
        'can_buy'           => false,
        'can_sell'          => true
    ])
    ->first();

/*
    Reminder products and there prices
*/

$product_model = ProductModel::create([
        'name'      => 'Frais rappel paiement',
        'family_id' => 1,
        'can_buy'   => false,
        'can_sell'  => true
    ])
    ->first();

$products_ids = [];
foreach([1, 2, 3, 4] as $reminder_lvl) {
    $map_number_label = [
        1 => '1er',
        2 => '2ème',
        3 => '3ème',
        4 => '4ème'
    ];

    $reminder_product = Product::create([
            'product_model_id'  => $product_model['id'],
            'label'             => "Frais rappel paiement ($map_number_label[$reminder_lvl])",
            'sku'               => "PAYMENT_REMINDER_L$reminder_lvl",
            'can_buy'           => false,
            'can_sell'          => true
        ])
        ->read(['sku'])
        ->first();

    $products_ids[] = $reminder_product['id'];

    $setting_code = "payment_reminder.level_$reminder_lvl.sku";
    Setting::assert_value('realestate', 'features', $setting_code);
    Setting::set_value('realestate', 'features', $setting_code, $reminder_product['sku']);
}

$price_list_category = PriceListCategory::create([
    'name' => 'Défaut'
])
    ->first();

$year = intval(date('Y'));

$new_date_from = mktime(0, 0, 0, 1, 1, $year);
$new_date_to = mktime(23, 59, 59, 12, 31, $year);

$price_list = PriceList::create([
    'name'          => "Défaut $year",
    'description'   => "Liste de prix par défaut pour l'année $year",
    'date_from'     => $new_date_from,
    'date_to'       => $new_date_to
])
    ->first();

foreach($products_ids as $product_id) {
    Price::create([
        'price_list_id'         => $price_list['id'],
        'price'                 => 0.0,
        'accounting_rule_id'    => 1,
        'product_id'            => $product_id
    ]);
}
