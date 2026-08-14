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
use Okay\Modules\Sviat\OrdersExport\Services\SeparateSessionAdminIdentity;
use Okay\Modules\Sviat\OrdersExport\Services\SharedSessionAdminIdentity;

return [
    // Композиційний корінь: рушій визначається один раз, тут. Далі
    // контролери працюють з портом і про різницю не знають. За номером
    // версії рушії не розрізнити — обидва звуть себе 4.5.2.
    AdminIdentity::class => [
        'class' => class_exists('Okay\\Core\\Security\\SessionNames')
            ? SeparateSessionAdminIdentity::class
            : SharedSessionAdminIdentity::class,
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
