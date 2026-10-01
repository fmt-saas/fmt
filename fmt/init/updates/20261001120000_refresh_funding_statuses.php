<?php

use realestate\sale\pay\Funding;

Funding::search()->do('refresh_status');
