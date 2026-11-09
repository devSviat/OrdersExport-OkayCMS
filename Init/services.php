<?php


namespace Okay\Modules\Sviat\OrdersExport;

use Okay\Core\EntityFactory;
use Okay\Core\Modules\Modules;
use Okay\Core\OkayContainer\Reference\ServiceReference as SR;
use Okay\Core\Request;
use Okay\Core\Settings;
use Okay\Modules\Sviat\OrdersExport\Backend\Helpers\BackendOrdersExportHelper;
use Okay\Modules\Sviat\OrdersExport\Extenders\BackendExtender;
use Okay\Modules\Sviat\OrdersExport\Helpers\NovaPoshtaWaybills;
use Okay\Modules\Sviat\OrdersExport\Security\AdminIdentity;

return [
    AdminIdentity::class => [
        'class' => AdminIdentity::class,
        'arguments' => [],
    ],
    NovaPoshtaWaybills::class => [
        'class' => NovaPoshtaWaybills::class,
        'arguments' => [
            new SR(Modules::class),
            new SR(EntityFactory::class),
        ],
    ],
    BackendOrdersExportHelper::class => [
        'class' => BackendOrdersExportHelper::class,
        'arguments' => [
            new SR(EntityFactory::class),
            new SR(Request::class),
            new SR(Settings::class),
            new SR(Modules::class),
            new SR(NovaPoshtaWaybills::class),
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
