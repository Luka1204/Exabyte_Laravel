<?php

return [
    'secret' => env('JWT_SECRET'),
    'ttl' => (int) env('JWT_TTL', 900),
    'refresh_ttl' => (int) env('JWT_REFRESH_TTL', 2592000),
];
