<?php

use fmt\setting\Setting;

// Plan de rappels
Setting::assert_value('realestate', 'features', 'payment_reminder.count', 4);
Setting::assert_value('realestate', 'features', 'payment_reminder.level_1.product_id');
Setting::assert_value('realestate', 'features', 'payment_reminder.level_2.product_id');
Setting::assert_value('realestate', 'features', 'payment_reminder.level_3.product_id');
Setting::assert_value('realestate', 'features', 'payment_reminder.level_4.product_id');
