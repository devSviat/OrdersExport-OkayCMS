<?php

namespace Okay\Modules\Sviat\OrdersExport\Backend\Controllers;

use Okay\Controllers\AbstractController;
use Okay\Core\Managers;
use Okay\Modules\Sviat\OrdersExport\Security\AdminIdentity;
use Okay\Modules\Sviat\OrdersExport\Security\RequestOrigin;
use Okay\Entities\ManagersEntity;
use Okay\Modules\Sviat\OrdersExport\Backend\Helpers\BackendOrdersExportHelper;

/** AJAX-контролер експорту замовлень. */
class OrdersExportAjaxController extends AbstractController
{
    /** Запускає експорт замовлень у CSV. */
    public function exportOrders(
        AdminIdentity $adminIdentity,
        Managers $managers,
        ManagersEntity $managersEntity,
        BackendOrdersExportHelper $backendOrdersExportHelper
    ) {
        // Маршрут оголошений як to_front, тож запит іде через вітрину повз
        // авторизацію бекенду. Перевіряємо дві різні речі: *хто* звертається
        // (AdminIdentity — рушії зберігають бекендову сесію по-різному) і
        // *звідки* (RequestOrigin разом із вимогою POST — кука адмінки має
        // SameSite=Lax, тож єдиний міжсайтовий шлях до неї — GET-навігація).
        if (!$this->request->method('post')) {
            $this->response->setStatusCode(405);
            $this->response->setContent(json_encode(['error' => 'Method Not Allowed']), RESPONSE_JSON);
            return;
        }

        if (!RequestOrigin::isFromThisSite()) {
            $this->response->setStatusCode(403);
            $this->response->setContent(json_encode(['error' => 'Forbidden']), RESPONSE_JSON);
            return;
        }

        $adminLogin = $adminIdentity->login();
        if (empty($adminLogin)) {
            $this->response->setStatusCode(401);
            $this->response->setContent(json_encode(['error' => 'Unauthorized']), RESPONSE_JSON);
            return;
        }

        $manager = $managersEntity->get($adminLogin);
        if (!$manager || !$managers->access('export', $manager)) {
            $this->response->setStatusCode(403);
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
