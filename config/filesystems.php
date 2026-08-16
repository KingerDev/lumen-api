<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Default Filesystem Disk
    |--------------------------------------------------------------------------
    |
    | Here you may specify the default filesystem disk that should be used
    | by the framework. The "local" disk, as well as a variety of cloud
    | based disks are available to your application for file storage.
    |
    */

    'default' => env('FILESYSTEM_DISK', 'local'),

    /*
    |--------------------------------------------------------------------------
    | Filesystem Disks
    |--------------------------------------------------------------------------
    |
    | Below you may configure as many filesystem disks as necessary, and you
    | may even configure multiple disks for the same driver. Examples for
    | most supported storage drivers are configured here for reference.
    |
    | Supported drivers: "local", "ftp", "sftp", "s3"
    |
    */

    'disks' => [

        'local' => [
            'driver' => 'local',
            'root' => storage_path('app/private'),
            'serve' => true,
            'throw' => false,
            'report' => false,
        ],

        'public' => [
            'driver' => 'local',
            'root' => storage_path('app/public'),
            'url' => rtrim(env('APP_URL', 'http://localhost'), '/').'/storage',
            'visibility' => 'public',
            'throw' => false,
            'report' => false,
        ],

        's3' => [
            'driver' => 's3',
            'key' => env('AWS_ACCESS_KEY_ID'),
            'secret' => env('AWS_SECRET_ACCESS_KEY'),
            'region' => env('AWS_DEFAULT_REGION'),
            'bucket' => env('AWS_BUCKET'),
            'url' => env('AWS_URL'),
            'endpoint' => env('AWS_ENDPOINT'),
            'use_path_style_endpoint' => env('AWS_USE_PATH_STYLE_ENDPOINT', false),
            'throw' => false,
            'report' => false,
        ],

        /*
         * Cloudflare R2.
         *
         * R2 speaks S3, but three settings differ from AWS and silently break
         * presigned URLs when wrong:
         *   - region must be the literal string "auto" (R2 has no regions)
         *   - path-style addressing is required
         *   - the endpoint is https://<account-id>.r2.cloudflarestorage.com
         *
         * `throw` is on so a misconfigured bucket fails loudly at the first
         * request instead of quietly returning null URLs.
         */
        'r2' => [
            'driver' => 's3',

            // Credentials take either prefix: R2_* wins, AWS_* is the fallback.
            // Reaching for the AWS names is the natural instinct when wiring an
            // S3-compatible bucket into Laravel, and refusing them buys nothing.
            // `?:` and not env()'s second argument: the default only applies to
            // an *undefined* variable, so a declared-but-empty R2_ACCESS_KEY_ID
            // (exactly what a copied .env.example leaves behind) would shadow
            // the AWS_* value instead of falling through to it.
            'key' => env('R2_ACCESS_KEY_ID') ?: env('AWS_ACCESS_KEY_ID'),
            'secret' => env('R2_SECRET_ACCESS_KEY') ?: env('AWS_SECRET_ACCESS_KEY'),
            'bucket' => env('R2_BUCKET') ?: env('AWS_BUCKET'),
            'endpoint' => env('R2_ENDPOINT') ?: env('AWS_ENDPOINT'),

            // These two deliberately do NOT accept AWS_*. A stock Laravel .env
            // ships AWS_DEFAULT_REGION=us-east-1 and
            // AWS_USE_PATH_STYLE_ENDPOINT=false — precisely the two values that
            // break R2 while the config still looks complete.
            'region' => env('R2_REGION', 'auto'),
            'use_path_style_endpoint' => env('R2_USE_PATH_STYLE_ENDPOINT', true),
            'throw' => true,
            'report' => false,

            // How long the signed URLs we hand the app stay valid.
            'upload_ttl' => (int) env('R2_UPLOAD_URL_TTL', 900),
            'download_ttl' => (int) env('R2_DOWNLOAD_URL_TTL', 3600),
        ],

    ],

    /*
    |--------------------------------------------------------------------------
    | Symbolic Links
    |--------------------------------------------------------------------------
    |
    | Here you may configure the symbolic links that will be created when the
    | `storage:link` Artisan command is executed. The array keys should be
    | the locations of the links and the values should be their targets.
    |
    */

    'links' => [
        public_path('storage') => storage_path('app/public'),
    ],

];
