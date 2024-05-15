<?php

declare(strict_types=1);


use MazeDEV\DatabaseConnector\PersistentPDO;
use MazeDEV\DatabaseConnector\PersistentPDOFactory;

return [
    'dependencies' => 
    [
        'aliases' => [],
        'invokables' => [],
        'factories' => 
        [
            PersistentPDO::class => PersistentPDOFactory::class,
        ],
    ],
];
