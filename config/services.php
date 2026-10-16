<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Resend, Postmark, AWS, and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    /*
     * Sign in with Apple. client_id is the app's bundle id — the `aud` of every
     * identity token. The key (.p8 from Apple Developer → Keys) is only needed
     * to revoke an account at Apple when it is deleted; sign-in works without.
     */
    'apple' => [
        'client_id' => env('APPLE_CLIENT_ID', 'com.martinbalaz.lumen'),
        'team_id' => env('APPLE_TEAM_ID'),
        'key_id' => env('APPLE_KEY_ID'),
        // The .p8 contents; newlines may be written as literal \n in Coolify.
        'private_key' => env('APPLE_PRIVATE_KEY'),
    ],

    /*
     * Public contact shown on /privacy and /support — App Review and GDPR both
     * need a way to reach the person behind the app.
     */
    'lumen' => [
        'contact_email' => env('LUMEN_CONTACT_EMAIL'),
    ],

];
