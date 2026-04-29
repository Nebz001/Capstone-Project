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
    | Submissions Disk
    |--------------------------------------------------------------------------
    |
    | Disk used for organization submission attachments (registration / renewal
    | requirement files, activity proposal files, after-activity report files,
    | etc.). Defaults to the local `public` disk so the app works out-of-the-box
    | with `php artisan storage:link`. Set SUBMISSIONS_STORAGE_DISK=supabase
    | (or any other configured disk) to switch storage backends without
    | touching application code.
    |
    */

  'submissions_disk' => env('SUBMISSIONS_STORAGE_DISK', 'public'),

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

    // keep existing disks...

    /*
        | Supabase Storage exposes an S3-compatible endpoint. The credentials
        | MUST be the dedicated S3 Access Key ID + Secret Access Key generated
        | in Supabase Dashboard → Storage → S3 Configuration → Access Keys.
        | Do NOT reuse the project anon key, the service-role JWT, or any
        | other Supabase API key here — the AWS SDK will reject them as
        | invalid SigV4 credentials and fall back to InstanceProfileProvider,
        | which silently tries the EC2 metadata endpoint and hangs your
        | request until PHP's max_execution_time elapses.
        */
    'supabase' => [
      'driver'                  => 's3',
      'key'                     => env('SUPABASE_STORAGE_ACCESS_KEY_ID'),
      'secret'                  => env('SUPABASE_STORAGE_SECRET_ACCESS_KEY'),
      'region'                  => env('SUPABASE_STORAGE_REGION', 'ap-northeast-2'),
      'bucket'                  => env('SUPABASE_STORAGE_BUCKET', 'organization-requirements'),
      'endpoint'                => env('SUPABASE_STORAGE_ENDPOINT'),
      'use_path_style_endpoint' => true,
      'throw'                   => true,
    ],

    'public' => [
      'driver' => 'local',
      'root' => storage_path('app/public'),
      'url' => rtrim(env('APP_URL', 'http://localhost'), '/') . '/storage',
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
