<?php

namespace Okay\Modules\Sviat\OrdersExport;

return [
    'Sviat_OrdersExport_exportOrders' => [
        'slug' => 'backend/orders-export/ajax/exportOrders',
        'to_front' => true,
        'params' => [
            'controller' => __NAMESPACE__ . '\Backend\Controllers\OrdersExportAjaxController',
            'method' => 'exportOrders',
        ],
    ],
];
