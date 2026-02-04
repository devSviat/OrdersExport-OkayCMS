<?php


namespace Okay\Modules\Sviat\OrdersExport\Init;

use Okay\Core\Modules\AbstractInit;

class Init extends AbstractInit
{
    public function install()
    {
        $this->setBackendMainController('OrdersExportAdmin');
    }

    public function init()
    {
        $this->registerBackendController('OrdersExportAdmin');
        $this->addBackendControllerPermission('OrdersExportAdmin', 'export');

        $this->extendBackendMenu(
            'left_orders',
            [
                'left_orders_export_title' => ['OrdersExportAdmin'],
            ]
        );
    }
}
