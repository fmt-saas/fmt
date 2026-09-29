<?php

use communication\template\Template;
use communication\template\TemplatePart;

$template = Template::search([
        ['code', '=', 'expense_statement_correspondence'],
        ['category', '=', 'general'],
        ['type', '=', 'document']
    ])
    ->read(['id'])
    ->first();

if(!$template) {
    return;
}

$introduction = TemplatePart::search([
        ['template_id', '=', $template['id']],
        ['name', '=', 'introduction']
    ])
    ->read(['id', 'value', 'variables'])
    ->first();

if(!$introduction) {
    return;
}

$variables = json_decode($introduction['variables'], true);
if(!is_array($variables)) {
    $variables = [];
}

if(!in_array('document_date', $variables, true)) {
    $variables[] = 'document_date';
}

$variables = json_encode($variables);

TemplatePart::id($introduction['id'])->update([
    'name'      => 'introduction_period_end',
    'variables' => $variables
]);

TemplatePart::create([
    'name'        => 'introduction_document_date',
    'value'       => $introduction['value'],
    'template_id' => $template['id'],
    'variables'   => $variables
]);
