<?php


namespace Okay\Modules\Sviat\OrdersExport;

use Okay\Core\EntityFactory;
use Okay\Core\Modules\Modules;
use Okay\Core\OkayContainer\Reference\ServiceReference as SR;
use Okay\Core\Request;
use Okay\Core\Settings;
use Okay\Modules\Sviat\OrdersExport\Backend\Helpers\BackendOrdersExportHelper;
use Okay\Modules\Sviat\OrdersExport\Carriers\AllCarrierNumbers;
use Okay\Modules\Sviat\OrdersExport\Carriers\CarrierNumbers;
use Okay\Modules\Sviat\OrdersExport\Carriers\DeliveryCarriers;
use Okay\Modules\Sviat\OrdersExport\Carriers\NovaPoshtaWaybills;
use Okay\Modules\Sviat\OrdersExport\Carriers\UkrposhtaBarcodes;
use Okay\Modules\Sviat\OrdersExport\Extenders\BackendExtender;
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
    UkrposhtaBarcodes::class => [
        'class' => UkrposhtaBarcodes::class,
        'arguments' => [
            new SR(Modules::class),
        ],
    ],
    DeliveryCarriers::class => [
        'class' => DeliveryCarriers::class,
        'arguments' => [
            new SR(EntityFactory::class),
        ],
    ],
    CarrierNumbers::class => [
        'class' => AllCarrierNumbers::class,
        'arguments' => [
            new SR(NovaPoshtaWaybills::class),
            new SR(UkrposhtaBarcodes::class),
            new SR(DeliveryCarriers::class),
        ],
    ],
    BackendOrdersExportHelper::class => [
        'class' => BackendOrdersExportHelper::class,
        'arguments' => [
            new SR(EntityFactory::class),
            new SR(Request::class),
            new SR(Settings::class),
            new SR(Modules::class),
            new SR(CarrierNumbers::class),
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
