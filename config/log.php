<?php

return [
    "default"=> "app",
    "app"=>[
        "handler"=> '\Monolog\Handler\RotatingFileHandler',
        "params"=>[
            'filename'=> var_path('logs/app.log')
        ]
    ]
];

