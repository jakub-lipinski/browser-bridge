<?php

return [
    'api_token' => env('BROWSERBRIDGE_API_TOKEN'),

    'max_snapshot_payload_bytes' => env('BROWSERBRIDGE_MAX_SNAPSHOT_PAYLOAD_BYTES', 512 * 1024),
    'max_history_batch_size' => env('BROWSERBRIDGE_MAX_HISTORY_BATCH_SIZE', 100),
    'history_search_limit' => env('BROWSERBRIDGE_HISTORY_SEARCH_LIMIT', 50),
];
