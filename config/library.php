<?php

// Settings of the Manager Library application. Secrets come from the environment only (constitution Article IX).
return [
    'jwt_secret' => env('JWT_SECRET'),
    'covers_dir' => env('COVERS_DIR', storage_path('app/covers')),
    'admin_username' => env('ADMIN_USERNAME'),
    'admin_password' => env('ADMIN_PASSWORD'),
];
