<?php
# Author: Eduard Laas
# 2005 - 2026 SLAED
# License: MIT
# Website: slaed.net

return [
    'oauth' => [
        'active' => '0',
        'google' => [
            'active' => '0',
            'clientid' => '',
            'secret' => '',
            'scopes' => 'openid email profile',
            'auth' => 'https://accounts.google.com/o/oauth2/v2/auth',
            'token' => 'https://oauth2.googleapis.com/token',
            'userinfo' => 'https://openidconnect.googleapis.com/v1/userinfo',
            'jwks' => 'https://www.googleapis.com/oauth2/v3/certs',
            'iss' => 'https://accounts.google.com,accounts.google.com',
            'isstpl' => '',
            'tenant' => '',
            'prompt' => 'select_account',
        ],
        'microsoft' => [
            'active' => '0',
            'clientid' => '',
            'secret' => '',
            'scopes' => 'openid email profile',
            'auth' => 'https://login.microsoftonline.com/common/oauth2/v2.0/authorize',
            'token' => 'https://login.microsoftonline.com/common/oauth2/v2.0/token',
            'userinfo' => 'https://graph.microsoft.com/oidc/userinfo',
            'jwks' => 'https://login.microsoftonline.com/common/discovery/v2.0/keys',
            'iss' => '',
            'isstpl' => 'https://login.microsoftonline.com/{tid}/v2.0',
            'tenant' => 'common',
            'prompt' => 'select_account',
        ],
    ],
];
