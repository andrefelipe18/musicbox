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

    'landing' => [
        'brand' => 'MusicBox',
        'title' => 'MusicBox | Your musical universe in one place',
        'meta_description' => 'Your musical universe in one place. Catalog artists, albums and singles, track your listening and keep your discoveries with MusicBox.',
        'skip_to_content' => 'Skip to content',
        'home_label' => 'MusicBox, home page',
        'navigation' => [
            'label' => 'Main navigation',
            'about' => 'About MusicBox',
            'how_it_works' => 'How it works',
        ],
        'hero' => [
            'eyebrow' => 'For those who live for music',
            'heading' => 'Your music.',
            'heading_accent' => 'Your universe.',
            'description' => 'Organize your albums, track your listening and keep your discoveries close at hand.',
            'action' => 'Discover MusicBox',
            'image_alt' => 'Vinyl record with sleeves in violet and graphite tones',
            'image_caption' => 'A place for everything you listen to.',
        ],
        'features' => [
            'heading' => 'More than a list.',
            'heading_accent' => 'A collection of your own.',
            'description' => 'From longtime favorites to the next album that surprises you. Every discovery has its place.',
            'catalog' => [
                'heading' => 'Your catalog, organized',
                'description' => 'Bring together artists, albums, EPs and singles. Explore discographies without losing track of your discoveries.',
            ],
            'listening' => [
                'heading' => 'Every listen counts',
                'description' => 'Keep track of what you want to hear, what you are listening to and the records you have already heard.',
            ],
            'reviews' => [
                'heading' => 'Keep your impressions',
                'description' => 'Rate albums and write your notes. Remember what made a song worth another listen.',
            ],
        ],
        'workflow' => [
            'heading' => 'From discovery to your collection.',
            'description' => 'A simple way to keep track of the music that is part of your life.',
            'discover' => [
                'heading' => 'Find an artist',
                'description' => 'Search for the artists you love and explore their releases.',
            ],
            'collect' => [
                'heading' => 'Build your collection',
                'description' => 'Add the records you want to hear or already know.',
            ],
            'personalize' => [
                'heading' => 'Make it your own',
                'description' => 'Track your listening, ratings and personal notes.',
            ],
        ],
        'footer' => [
            'tagline' => 'Your musical universe in one place.',
            'copyright' => '© :year MusicBox',
        ],
    ],

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

    'dashboard' => [
        'filters' => 'Filters',
        'filters_heading' => 'Filter dashboard',
        'start_date' => 'Start date',
        'end_date' => 'End date',
        'period_summary' => 'Period summary',
        'new_users' => 'New users',
        'user_growth' => 'User growth',
        'ratings_recorded' => 'Ratings recorded',
        'ratings_per_day' => 'Ratings per day',
        'ratings' => 'Ratings',
        'average' => 'Average',
    ],

    'user_menu' => [
        'api_documentation' => 'API Documentation',
    ],

    'resources' => [
        'users' => [
            'label' => 'user',
            'plural_label' => 'users',
            'navigation_label' => 'Users',

            'fields' => [
                'name' => 'Name',
                'email' => 'Email',
                'email_verified_at' => 'Email verified',
                'password' => 'Password',
                'password_confirmation' => 'Confirm password',
                'created_at' => 'Created at',
                'updated_at' => 'Updated at',
            ],

            'sections' => [
                'identity' => [
                    'heading' => 'Identity',
                    'description' => 'Account and sign-in details for this person. Leave the password blank to keep the current one.',
                ],
            ],

            'verified' => 'Verified',
            'unverified' => 'Unverified',
        ],
    ],

];
