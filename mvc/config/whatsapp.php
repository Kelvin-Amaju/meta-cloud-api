<?php

declare(strict_types=1);

return [

    'version' => $_ENV['META_API_VERSION'],

    'app_id' => $_ENV['META_APP_ID'],

    'app_secret' => $_ENV['META_APP_SECRET'],

    'verify_token' => $_ENV['META_VERIFY_TOKEN'],

    'access_token' => $_ENV['META_ACCESS_TOKEN'],

    'phone_number_id' => $_ENV['META_PHONE_NUMBER_ID']

];