<?php
return [
    'Users.SimpleRbac.permissions' => [
        // Allow admins access to everything
        [
            'role' => 'admin',
            'allowed' => true
        ],

        // Allow everyone access to any non-prefixed actions
        [
            'prefix' => false,
            'allowed' => true
        ]
    ],
];
