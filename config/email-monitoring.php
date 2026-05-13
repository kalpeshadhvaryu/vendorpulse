<?php

return [

    'queue' => env('EMAIL_MONITORING_QUEUE', 'email-monitoring'),

    'default_mailbox_driver' => env('EMAIL_MONITORING_MAILBOX_DRIVER', 'imap'),

    'ocr' => [
        'enabled' => env('EMAIL_MONITORING_OCR_ENABLED', false),
        'default_provider' => env('EMAIL_MONITORING_OCR_PROVIDER', 'noop'),
    ],

    'extraction' => [
        'low_confidence_threshold' => (float) env('EMAIL_MONITORING_LOW_CONFIDENCE', 0.65),
    ],

];
