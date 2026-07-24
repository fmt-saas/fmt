<?php
use communication\template\Template;
use communication\template\TemplatePart;

$template = Template::search([
        ['code', '=', 'ownership_transfer_paragraph_1'],
        ['category_id', '=', 5],
        ['type_id', '=', 5]
    ])
    ->first();

TemplatePart::create([
    'name'          => 'refer_to_paragraph_2',
    'value'         => "<p>Veuillez vous référez au point correspondant dans le pagraphe 2.</p>",
    'template_id'   => $template['id'],
    'variables'     => '[]'
]);