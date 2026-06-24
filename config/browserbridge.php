<?php

return [
    'api_token' => env('BROWSERBRIDGE_API_TOKEN'),

    'max_devices' => env('BROWSERBRIDGE_MAX_DEVICES', 10),
    'max_bookmark_snapshot_payload_bytes' => env('BROWSERBRIDGE_MAX_BOOKMARK_SNAPSHOT_PAYLOAD_BYTES', 1024 * 1024),
    'max_tab_snapshot_payload_bytes' => env('BROWSERBRIDGE_MAX_TAB_SNAPSHOT_PAYLOAD_BYTES', 512 * 1024),
    'max_history_batch_size' => env('BROWSERBRIDGE_MAX_HISTORY_BATCH_SIZE', 500),
    'max_pending_tab_commands_per_target' => env('BROWSERBRIDGE_MAX_PENDING_TAB_COMMANDS_PER_TARGET', 100),
    'allow_same_device_tab_commands' => env('BROWSERBRIDGE_ALLOW_SAME_DEVICE_TAB_COMMANDS', false),
    'history_search_limit' => env('BROWSERBRIDGE_HISTORY_SEARCH_LIMIT', 50),
    'history_retention_days' => env('BROWSERBRIDGE_HISTORY_RETENTION_DAYS', 14),
    'tab_command_retention_days' => env('BROWSERBRIDGE_TAB_COMMAND_RETENTION_DAYS', 7),
];
