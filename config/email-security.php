<?php

return [
    // DKIM selectors probed when the user does not supply one. DNS cannot list selectors,
    // so this covers the common mail providers and hosting panels.
    'dkim_selectors' => array_values(array_filter(array_map('trim', explode(',', (string) env(
        'EMAIL_SECURITY_DKIM_SELECTORS',
        'default,google,selector1,selector2,k1,k2,k3,s1,s2,mail,dkim,smtp,zoho,zmail,mxvault,mandrill,mailjet,pm,sendgrid,amazonses,everlytickey1,everlytickey2,protonmail,protonmail2,protonmail3,fm1,fm2,fm3,hs1,hs2,key1,key2,dk,x,'
        .'brevo1,brevo2,mx,krs,smtpapi,sig1,ctct1,ctct2,zendesk1,zendesk2,kl,kl2,cm,titan1,hostingermail-a,hostingermail-b,hostingermail-c'
    ))))),

    'blacklist' => [
        'enabled' => (bool) env('EMAIL_SECURITY_BLACKLIST_ENABLED', true),

        // Spamhaus refuses queries arriving via public resolvers (8.8.8.8, 1.1.1.1, ...).
        // With a free/paid Data Query Service key the lookups go to the DQS zones instead.
        'spamhaus_dqs_key' => env('EMAIL_SECURITY_SPAMHAUS_DQS_KEY'),

        'ip_zones' => [
            'zen.spamhaus.org' => 'Spamhaus ZEN',
            'b.barracudacentral.org' => 'Barracuda',
            'bl.spamcop.net' => 'SpamCop',
            'psbl.surriel.com' => 'PSBL',
            'dnsbl-1.uceprotect.net' => 'UCEPROTECT L1',
            'bl.mailspike.net' => 'Mailspike',
        ],

        'domain_zones' => [
            'dbl.spamhaus.org' => 'Spamhaus DBL',
            'multi.surbl.org' => 'SURBL',
        ],

        // Permanently listed test entries. A zone that does not answer for its test entry is
        // unreachable from this resolver, so its "not listed" answers are reported as unverified.
        'domain_zone_test_entries' => [
            'dbl.spamhaus.org' => 'dbltest.com',
            'multi.surbl.org' => 'test.surbl.org',
        ],

        'max_ips' => (int) env('EMAIL_SECURITY_BLACKLIST_MAX_IPS', 2),
    ],

    'max_mx_hosts' => (int) env('EMAIL_SECURITY_MAX_MX_HOSTS', 5),

    // Send-a-test-email tool. Users send mail to <local>+vp-<token>@<domain>; the mailbox is read over IMAP.
    'test_mailbox' => [
        'address' => env('EMAIL_TEST_ADDRESS'),
        'host' => env('EMAIL_TEST_IMAP_HOST'),
        'port' => (int) env('EMAIL_TEST_IMAP_PORT', 993),
        'encryption' => env('EMAIL_TEST_IMAP_ENCRYPTION', 'ssl'),
        'username' => env('EMAIL_TEST_IMAP_USERNAME'),
        'password' => env('EMAIL_TEST_IMAP_PASSWORD'),
        'folder' => env('EMAIL_TEST_IMAP_FOLDER', 'INBOX'),
        'delete_after_read' => (bool) env('EMAIL_TEST_DELETE_AFTER_READ', true),
        // Only trust Authentication-Results added by this receiving server (e.g. mx.google.com).
        // Empty = trust the topmost Authentication-Results header.
        'trusted_authserv_id' => env('EMAIL_TEST_TRUSTED_AUTHSERV_ID'),
        'expires_minutes' => (int) env('EMAIL_TEST_EXPIRES_MINUTES', 30),
        'inbox_check_interval_seconds' => (int) env('EMAIL_TEST_INBOX_CHECK_INTERVAL', 5),
        'retention_days' => (int) env('EMAIL_TEST_RETENTION_DAYS', 7),
    ],

    'http_timeout_seconds' => (int) env('EMAIL_SECURITY_HTTP_TIMEOUT', 5),
];
