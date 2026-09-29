<?php

return [
    'name' => 'Auth',
    // Keep account mail asynchronous even when the general queue is synchronous.
    'mail_connection' => env('AUTH_MAIL_CONNECTION', env('QUEUE_CONNECTION') === 'redis' ? 'redis' : 'database'),
];
