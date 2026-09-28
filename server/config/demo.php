<?php

return [
    // Never enabled outside local/testing, even if this flag is accidentally deployed.
    'enabled' => (bool) env('DEMO_ENABLED', env('APP_ENV') === 'local'),
];
