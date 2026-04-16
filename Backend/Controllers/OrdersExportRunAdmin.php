<?php


namespace Okay\Modules\Sviat\OrdersExport\Backend\Controllers;

use Okay\Admin\Controllers\IndexAdmin;
use Okay\Core\Modules\Modules;
use Okay\Entities\BrandsEntity;
use Okay\Entities\OrderStatusEntity;

/**
 * Контролер адмін-панелі для сторінки експорту замовлень
 */
class OrdersExportRunAdmin extends IndexAdmin
{
    private string $exportFilesDir = 'backend/files/export/';

    /**
     * Відображає сторінку запуску експорту замовлень
     */
    public function fetch(
        OrderStatusEntity $orderStatusEntity,
        BrandsEntity $brandsEntity,
        Modules $modules
    ) {
        $defaultExportTtn = (string) $this->settings->get('sviat__orders_export__default_export_ttn');
        if (!in_array($defaultExportTtn, ['0', '1', '2'], true)) {
            $defaultExportTtn = '0';
        }
        $this->design->assign('orders_export_default_export_ttn', $defaultExportTtn);

        $this->design->assign('export_files_dir', $this->exportFilesDir);
        if (!is_writable($this->exportFilesDir)) {
            $this->design->assign('message_error', 'no_permission');
        }

        $statuses = $orderStatusEntity->find();
        $this->design->assign('statuses', $statuses);

        $brands = $brandsEntity->find(['limit' => $brandsEntity->count()]);
        $this->design->assign('brands', $brands);

        $isNovaPoshtaTrackingActive = $modules->isActiveModule('Sviat', 'NovaPoshtaTracking');
        $this->design->assign('is_nova_poshta_tracking_active', $isNovaPoshtaTrackingActive);

        $this->response->setContent($this->design->fetch('export_orders.tpl'));
    }
}
