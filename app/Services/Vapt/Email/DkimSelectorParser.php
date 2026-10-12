<?php

namespace App\Services\Vapt\Email;

/**
 * Turns whatever a user pastes into a DKIM selector: a bare selector ("google"),
 * the DNS name ("google._domainkey.example.com"), a tag ("s=google"), a
 * DKIM-Signature header, or a full block of message headers.
 */
class DkimSelectorParser
{
    public static function parse(string $input): ?string
    {
        $text = trim((string) preg_replace('/\s+/', ' ', $input));
        if ($text === '') {
            return null;
        }

        if (preg_match('/(?:^|\s)dkim-signature\s*:(.*)$/i', $text, $match)) {
            $text = $match[1];
        }

        if (preg_match('/(?:^|[;\s])s\s*=\s*([A-Za-z0-9][A-Za-z0-9._-]*)/i', $text, $match)) {
            return strtolower(rtrim($match[1], '.'));
        }

        if (preg_match('/^([A-Za-z0-9][A-Za-z0-9._-]*?)\._domainkey(?:\.|$)/i', $text, $match)) {
            return strtolower($match[1]);
        }

        return strtolower(rtrim($text, '.'));
    }
}
