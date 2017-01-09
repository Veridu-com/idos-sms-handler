<?php
/*
 * Copyright (c) 2012-2016 Veridu Ltd <https://veridu.com>
 * All rights reserved.
 */

declare(strict_types = 1);

use Cli\Utils\Env;

if (! defined('__VERSION__')) {
    define('__VERSION__', Env::asString('IDOS_VERSION', '1.0'));
}

$appSettings = [
    'debug'                             => Env::asBool('IDOS_DEBUG', false),
    'displayErrorDetails'               => Env::asBool('IDOS_DEBUG', false),
    'determineRouteBeforeAppMiddleware' => true,
    'log'                               => [
        'path' => Env::asString(
            'IDOS_LOG_FILE',
            sprintf(
                '%s/../log/sms.log',
                __DIR__
            )
        ),
        'level' => Monolog\Logger::DEBUG
    ],
    'sms' => [
       'endpoint'   => Env::asString('IDOS_SMS_ENDPOINT', 'https://api.smsapi.com/sms.do'),
       'username'   => Env::asString('IDOS_SMS_USER', '***REMOVED***'),
       'password'   => Env::asString('IDOS_SMS_PASS', '***REMOVED***')
    ],
    'gearman' => [
        'timeout' => 1000,
        'servers' => Env::asString('IDOS_GEARMAN_SERVERS', 'localhost:4730')
    ]
];
