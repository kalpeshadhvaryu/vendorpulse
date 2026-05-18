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

    'invoice_automation' => [
        // Keep disabled by default for production safety; enable explicitly per environment.
        'enabled' => env('EMAIL_MONITORING_INVOICE_AUTOMATION_ENABLED', false),
        'min_confidence' => (float) env('EMAIL_MONITORING_INVOICE_AUTOMATION_MIN_CONFIDENCE', 0.80),
        'allow_create_on_paid' => env('EMAIL_MONITORING_INVOICE_AUTOMATION_ALLOW_CREATE_ON_PAID', false),
    ],

];
