<?php

return [
    /*
    |--------------------------------------------------------------------------
    | SERP / rank provider
    |--------------------------------------------------------------------------
    | Supported: serpapi | google_cse | none
    | - serpapi: best for positions beyond page 1 (needs SERPAPI_KEY)
    | - google_cse: Google Programmable Search (top ~10 only; needs API key + CX)
    */
    'provider' => env('SEO_KEYWORDS_PROVIDER', 'none'),

    'serpapi' => [
        'key' => env('SERPAPI_KEY'),
        'endpoint' => env('SERPAPI_ENDPOINT', 'https://serpapi.com/search.json'),
    ],

    'google_cse' => [
        'key' => env('GOOGLE_CSE_API_KEY'),
        'cx' => env('GOOGLE_CSE_CX'),
        'endpoint' => env('GOOGLE_CSE_ENDPOINT', 'https://www.googleapis.com/customsearch/v1'),
    ],

    'http_timeout_seconds' => (int) env('SEO_KEYWORDS_HTTP_TIMEOUT', 20),
    'max_organic_results' => (int) env('SEO_KEYWORDS_MAX_ORGANIC', 100),

    // Days between automatic checks by priority
    'check_interval_days' => [
        'high' => (int) env('SEO_KEYWORDS_INTERVAL_HIGH', 1),
        'medium' => (int) env('SEO_KEYWORDS_INTERVAL_MEDIUM', 3),
        'low' => (int) env('SEO_KEYWORDS_INTERVAL_LOW', 7),
    ],

    // AI visibility engines listed in results (actual checks depend on provider data)
    'ai_engines' => array_values(array_filter(array_map('trim', explode(',', (string) env(
        'SEO_KEYWORDS_AI_ENGINES',
        'google_ai_overview,perplexity,chatgpt,gemini'
    ))))),
];
