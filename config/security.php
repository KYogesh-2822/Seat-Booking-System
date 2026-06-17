<?php

return [
    'alert_emails' => array_filter(array_map(
        'trim',
        explode(',', env('SECURITY_ALERT_EMAILS', ''))
    )),
];