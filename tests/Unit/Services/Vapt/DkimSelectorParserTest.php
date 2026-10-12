<?php

namespace Tests\Unit\Services\Vapt;

use App\Services\Vapt\Email\DkimSelectorParser;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class DkimSelectorParserTest extends TestCase
{
    /**
     * @return array<string, array{0:string, 1:?string}>
     */
    public static function inputs(): array
    {
        return [
            'bare selector' => ['Selector1', 'selector1'],
            'blank' => ['   ', null],
            'dns name' => ['google._domainkey.example.com', 'google'],
            'dns name with trailing dot' => ['s1._domainkey.example.com.', 's1'],
            'dotted selector dns name' => ['mail.2024._domainkey.example.com', 'mail.2024'],
            'tag' => ['s=k1', 'k1'],
            'header line' => [
                'DKIM-Signature: v=1; a=rsa-sha256; c=relaxed/relaxed; d=example.com; s=mail2024; t=1700000000; bh=abc=; b=XyZs=',
                'mail2024',
            ],
            'folded header without name' => ["v=1; a=rsa-sha256; d=example.com;\r\n\ts=hostingermail-a; h=from:to;", 'hostingermail-a'],
            'full headers prefer real signature over x-google' => [
                "X-Google-DKIM-Signature: v=1; d=1e100.net; s=20230601;\nDKIM-Signature: v=1; d=example.com; s=google;\nFrom: a@example.com",
                'google',
            ],
            'garbage stays invalid' => ['not a selector!', 'not a selector!'],
        ];
    }

    #[DataProvider('inputs')]
    public function test_parse(string $input, ?string $expected): void
    {
        $this->assertSame($expected, DkimSelectorParser::parse($input));
    }
}
