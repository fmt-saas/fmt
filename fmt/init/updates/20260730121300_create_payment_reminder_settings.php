<?php

use fmt\setting\Setting;

// Plan de rappels
Setting::assert_value('realestate', 'features', 'payment_reminder.count', 4);
Setting::assert_value('realestate', 'features', 'payment_reminder.level_1.sku');
Setting::assert_value('realestate', 'features', 'payment_reminder.level_2.sku');
Setting::assert_value('realestate', 'features', 'payment_reminder.level_3.sku');
Setting::assert_value('realestate', 'features', 'payment_reminder.level_4.sku');
