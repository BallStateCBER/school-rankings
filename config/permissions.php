<?php
return [
    'Users.SimpleRbac.permissions' => [
        // Allow admins access to everything
        [
            'role' => 'admin',
            'controller' => '*',
            'action' => '*',
            'allowed' => true
        ],

        // Allow everyone access to any non-prefixed actions
        [
            'prefix' => false,
            'plugin' => false,
            'controller' => '*',
            'action' => '*',
            'allowed' => true
        ],

        [
            'plugin' => 'CakeDC/Users',
            'controller' => 'Users',
            'action' => 'logout',
            'allowed' => true
        ]
    ],
];
