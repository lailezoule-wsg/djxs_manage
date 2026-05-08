<?php

return [
    'task' => [
        'status' => [
            'pending' => 0,
            'running' => 1,
            'completed' => 2,
            'paused' => 3,
            'cancelled' => 4,
            'deleted' => -1,
        ],
        'type' => [
            'novel' => 1,
            'drama' => 2,
        ],
    ],
    
    'source' => [
        'status' => [
            'disabled' => 0,
            'enabled' => 1,
        ],
        'type' => [
            'novel' => 1,
            'drama' => 2,
        ],
    ],
    
    'log' => [
        'level' => [
            'debug' => 1,
            'info' => 2,
            'warning' => 3,
            'error' => 4,
        ],
    ],
    
    'spider' => [
        'default_delay' => 2,
        'max_concurrent' => 10,
        'timeout' => 30,
    ],
];