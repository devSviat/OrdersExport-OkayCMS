<?php

namespace Okay\Modules\Sviat\OrdersExport\Backend\Controllers;

use Okay\Controllers\AbstractController;
use Okay\Core\Managers;
use Okay\Entities\ManagersEntity;
use Okay\Modules\Sviat\OrdersExport\Backend\Helpers\BackendOrdersExportHelper;

/** AJAX-контролер експорту замовлень. */
class OrdersExportAjaxController extends AbstractController
{
    /** Запускає експорт замовлень у CSV. */
    public function exportOrders(
        Managers $managers,
        ManagersEntity $managersEntity,
        BackendOrdersExportHelper $backendOrdersExportHelper
    ) {
        if (empty($_SESSION['admin'])) {
            $this->response->setContent(json_encode(['error' => 'Unauthorized']), RESPONSE_JSON);
            return;
        }

        $manager = $managersEntity->get($_SESSION['admin']);
        if (!$manager || !$managers->access('export', $manager)) {
            $this->response->setContent(json_encode(['error' => 'Access denied']), RESPONSE_JSON);
            return;
        }

        $columnsNames = $backendOrdersExportHelper->getColumnsNames();
        $configParams = $backendOrdersExportHelper->getConfigParams();

        $columnDelimiter = $configParams->column_delimiter;
        $ordersCount = $configParams->orders_count;
        $exportFilesDir = $configParams->export_files_dir;
        $filename = $configParams->filename;

        $result = $backendOrdersExportHelper->setUp(
            $exportFilesDir,
            $filename,
            $columnsNames,
            $columnDelimiter,
            $ordersCount
        );
        
        list($filter, $page) = $result;

        $orders = $backendOrdersExportHelper->fetchOrders($filter);
        $purchases = $backendOrdersExportHelper->attachPurchases($orders);
        $statuses = $backendOrdersExportHelper->attachStatuses($orders);

        $data = $backendOrdersExportHelper->exportRun(
            $exportFilesDir,
            $filename,
            $orders,
            $purchases,
            $statuses,
            $filter,
            $columnsNames,
            $columnDelimiter,
            $ordersCount,
            $page
        );

        if ($data) {
            $this->response->setContent(json_encode($data), RESPONSE_JSON);
        }
    }
}
