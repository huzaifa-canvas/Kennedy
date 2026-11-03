<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Firebase Cloud Messaging
    |--------------------------------------------------------------------------
    |
    | Leave FCM_ENABLED off until the service account JSON is in place. The
    | database notification channel keeps the in-app feed working either way;
    | switching this on simply adds push delivery on top of it.
    |
    */

    'enabled' => env('FCM_ENABLED', false),

    'project_id' => env('FCM_PROJECT_ID'),

    // Absolute path to the Firebase service account JSON file.
    'credentials' => env('FCM_CREDENTIALS', storage_path('app/firebase/service-account.json')),

    // Notifications older than this are removed by the daily saverr:cron job.
    'prune_after_days' => env('NOTIFICATIONS_PRUNE_DAYS', 15),
];
