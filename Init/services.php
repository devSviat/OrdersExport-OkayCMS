<?php


namespace Okay\Modules\Sviat\OrdersExport;

use Okay\Core\EntityFactory;
use Okay\Core\Modules\Modules;
use Okay\Core\OkayContainer\Reference\ServiceReference as SR;
use Okay\Core\Request;
use Okay\Core\Settings;
use Okay\Modules\Sviat\OrdersExport\Backend\Helpers\BackendOrdersExportHelper;
use Okay\Modules\Sviat\OrdersExport\Extenders\BackendExtender;
use Okay\Modules\Sviat\OrdersExport\Services\AdminIdentity;

return [
    AdminIdentity::class => [
        'class' => AdminIdentity::class,
        'arguments' => [],
    ],
    BackendOrdersExportHelper::class => [
        'class' => BackendOrdersExportHelper::class,
        'arguments' => [
            new SR(EntityFactory::class),
            new SR(Request::class),
            new SR(Settings::class),
            new SR(Modules::class),
        ],
    ],
    BackendExtender::class => [
        'class' => BackendExtender::class,
        'arguments' => [
            new SR(Settings::class),
            new SR(Request::class),
            new SR(EntityFactory::class),
        ],
    ],
];
