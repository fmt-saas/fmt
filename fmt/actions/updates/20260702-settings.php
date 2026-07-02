<?php
use fmt\setting\Setting;

['orm' => $orm] = eQual::inject(['orm']);

$events = $orm->disableEvents();
// nombre de jours après dépassement de la date d'échéance
Setting::assert_value('realestate', 'features', 'payment_reminder.first_delay_days', 2);
// délai d'attente avant envoi de deux rappels successifs
Setting::assert_value('realestate', 'features', 'payment_reminder.repeat_delay_days', 15);
// montant minimum pour un rappel
Setting::assert_value('realestate', 'features', 'payment_reminder.min_amount', 50);
Setting::assert_value('realestate', 'features', 'payment_reminder.max_reminder_level', 2);
Setting::assert_value('realestate', 'features', 'payment_reminder.is_first_reminder_billable', true);
Setting::assert_value('realestate', 'features', 'payment_reminder.auto_generate_correspondence', false);
// #todo to confirm
Setting::assert_value('realestate', 'features', 'payment_reminder.auto_generate_accounting_entries', false);


// to be configured by condominium
Setting::assert_value('realestate', 'features', 'reminder_penalty.fixed_fee', 0.0);
Setting::assert_value('realestate', 'features', 'reminder_penalty.percentage_fee', 0.0);
Setting::assert_value('realestate', 'features', 'reminder_penalty.interest_rate_monthly', 0.0);
// Setting::assert_value('realestate', 'features', 'reminder_penalty.accounting_account_id', true);


$orm->enableEvents($events);
