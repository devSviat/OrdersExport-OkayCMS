<?php


namespace Okay\Modules\Sviat\OrdersExport;

use Okay\Core\EntityFactory;
use Okay\Core\Modules\Modules;
use Okay\Core\OkayContainer\Reference\ServiceReference as SR;
use Okay\Core\Request;
use Okay\Modules\Sviat\OrdersExport\Backend\Helpers\BackendOrdersExportHelper;

return [
    BackendOrdersExportHelper::class => [
        'class' => BackendOrdersExportHelper::class,
        'arguments' => [
            new SR(EntityFactory::class),
            new SR(Request::class),
            new SR(Modules::class),
        ],
    ],
];
