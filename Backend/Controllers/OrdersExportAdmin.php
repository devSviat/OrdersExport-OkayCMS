<?php


namespace Okay\Modules\Sviat\OrdersExport\Backend\Controllers;

use Okay\Admin\Controllers\IndexAdmin;
use Okay\Core\Modules\Modules;
use Okay\Entities\OrderStatusEntity;
use Okay\Modules\Sviat\OrdersExport\Backend\Helpers\BackendOrdersExportHelper;

/**
 * Контролер адмін-панелі для експорту замовлень
 */
class OrdersExportAdmin extends IndexAdmin
{
    private string $exportFilesDir = 'backend/files/export/';

    /**
     * Відображає сторінку експорту замовлень
     * 
     * @param OrderStatusEntity $orderStatusEntity
     * @param BackendOrdersExportHelper $backendOrdersExportHelper
     * @param Modules $modules
     */
    public function fetch(
        OrderStatusEntity $orderStatusEntity,
        BackendOrdersExportHelper $backendOrdersExportHelper,
        Modules $modules
    ) {
        $this->design->assign('export_files_dir', $this->exportFilesDir);
        if (!is_writable($this->exportFilesDir)) {
            $this->design->assign('message_error', 'no_permission');
        }

        $statuses = $orderStatusEntity->find();
        $this->design->assign('statuses', $statuses);

        $isNovaPoshtaTrackingActive = $modules->isActiveModule('Sviat', 'NovaPoshtaTracking');
        $this->design->assign('is_nova_poshta_tracking_active', $isNovaPoshtaTrackingActive);

        $this->response->setContent($this->design->fetch('export_orders.tpl'));
    }
}
