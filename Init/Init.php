<?php


namespace Okay\Modules\Sviat\OrdersExport\Init;

use Okay\Admin\Helpers\BackendOrdersHelper;
use Okay\Core\Modules\AbstractInit;
use Okay\Entities\OrdersEntity;
use Okay\Modules\Sviat\OrdersExport\Extenders\BackendExtender;
use Okay\Modules\Sviat\OrdersExport\ExtendsEntities\OrdersEntityExtend;

class Init extends AbstractInit
{
    public function install()
    {
        $this->setBackendMainController('OrdersExportAdmin');
    }

    public function init()
    {
        $this->registerBackendController('OrdersExportAdmin');
        $this->registerBackendController('OrdersExportRunAdmin');
        $this->addBackendControllerPermission('OrdersExportAdmin', 'export');
        $this->addBackendControllerPermission('OrdersExportRunAdmin', 'export');
        $this->registerChainExtension(
            [BackendOrdersHelper::class, 'buildFilter'],
            [BackendExtender::class, 'buildFilter']
        );
        $this->registerChainExtension(
            [BackendOrdersHelper::class, 'buildCountStatusesFilter'],
            [BackendExtender::class, 'buildCountStatusesFilter']
        );
        $this->registerEntityFilter(OrdersEntity::class, 'has_ttn', OrdersEntityExtend::class, 'filter__has_ttn');
        $this->registerEntityFilter(OrdersEntity::class, 'brand_ids', OrdersEntityExtend::class, 'filter__brand_ids');

        $this->extendBackendMenu(
            'left_orders',
            [
                'left_orders_export_title' => ['OrdersExportRunAdmin'],
            ]
        );
    }
}
