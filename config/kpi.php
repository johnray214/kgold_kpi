<?php

return [
    // Timezone used for "days" on the dashboard. All API data is stored in UTC.
    'timezone' => env('KPI_TIMEZONE', 'Asia/Manila'),

    // Active pages tracked by default (tokens can be managed in /settings).
    'tracked_pages' => [
        ['fb_page_id' => '724698644061014', 'name' => 'Brusko Capsule Lakas', 'product' => 'Brusko Capsule'],
        ['fb_page_id' => '795172533673231', 'name' => 'Leanne’s Brusko Capsule', 'product' => 'Brusko Capsule'],
        ['fb_page_id' => '749648171561018', 'name' => 'Brusko Capsule Lodi Lakas', 'product' => 'Brusko Capsule'],
        ['fb_page_id' => '697376470132791', 'name' => 'Brusko Capsule Performance Boost', 'product' => 'Brusko Capsule'],
        ['fb_page_id' => '710893182116089', 'name' => 'Brusko Capsule Laban All Day', 'product' => 'Brusko Capsule'],
        ['fb_page_id' => '705817595954585', 'name' => 'Brusko Capsule Para sa Laban', 'product' => 'Brusko Capsule'],
        ['fb_page_id' => '1371179506076421', 'name' => 'Brusko Capsule Performance Boost PH', 'product' => 'Brusko Capsule'],
        ['fb_page_id' => '1412772708592115', 'name' => 'Brusko Capsule Lodi Lakas PH', 'product' => 'Brusko Capsule'],
    ],

    // Default page tokens from .env (applied to KPI_PAGE_ID on first seed).
    'default_page' => [
        'fb_page_id' => env('KPI_PAGE_ID'),
        'name' => env('KPI_PAGE_NAME'),
        'product' => env('KPI_PAGE_PRODUCT'),
        'botcake_token' => env('BOTCAKE_TOKEN'),
        'pancake_token' => env('PANCAKE_TOKEN'),
    ],

    'botcake' => [
        'base_url' => 'https://botcake.io/api/public_api/v1',
    ],

    'pancake' => [
        'base_url' => 'https://pages.fm/api/public_api',
    ],

    'pos' => [
        'base_url' => 'https://pos.pages.fm/api/v1',
        'api_key' => env('POS_API_KEY'),
        'shop_id' => env('POS_SHOP_ID'),
        'herbal_api_key' => env('POS_HERBAL_API_KEY'),
        'herbal_shop_id' => env('POS_HERBAL_SHOP_ID'),
        // Status codes excluded from sales (7 = deleted/trash; 6 = canceled is included in Total POS Orders)
        'excluded_statuses' => [7],
    ],

    // Attribution windows (hours) — proposals from the requirements brief.
    'attribution' => [
        'broadcast_hours' => 24,
        'sequence_hours' => 48,
        // Botcake/Pancake sender names that are automation, not humans.
        'bot_senders' => ['Botcake', 'BotcakeAI', 'POS'],
        'ai_sender' => 'BotcakeAI',
        // POS order note prefixes that mean telesales closed it.
        'telesales_markers' => ['[EasyDial'],
    ],

    // Label used when Botcake gives no failure reason.
    'failed_reason_label' => 'Account suspended / unavailable',
];
