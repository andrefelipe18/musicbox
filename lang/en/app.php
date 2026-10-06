<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Application Language Lines
    |--------------------------------------------------------------------------
    |
    | English source strings for MusicBox. Serves as the fallback locale, so a
    | missing pt_BR key degrades to English instead of leaking the raw key.
    |
    */

    'welcome' => 'Welcome',

    'login' => [
        'heading' => 'Welcome back',
        'subheading' => 'Use your admin account to continue.',
        'email' => 'Email',
        'email_placeholder' => 'name@example.com',
        'password' => 'Password',
        'submit' => 'Log in',
        'show_password' => 'Show password',
        'hide_password' => 'Hide password',
        'remember_me' => 'Remember me',
        'forgot_password' => 'Forgot your password?',
        'brand' => 'MusicBox',
        'tagline' => 'Music catalog',
        'quick_login' => 'Quick login',

        'select_admin' => 'Select an administrator',
    ],

    'admin_create' => [
        'email_prompt' => 'Enter the administrator email',
        'invalid_email' => 'Enter a valid email address.',
        'duplicate_email' => 'An administrator with email :email already exists.',
        'created' => 'Administrator created. Save the generated password below.',
        'email_output' => 'Email: :email',
        'password_output' => 'Generated password:',
    ],

];
