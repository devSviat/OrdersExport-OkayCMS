<?php


namespace Okay\Modules\Sviat\OrdersExport\Backend\Helpers;

use Okay\Core\EntityFactory;
use Okay\Core\Modules\Extender\ExtenderFacade;
use Okay\Core\Modules\Modules;
use Okay\Core\Request;
use Okay\Core\Settings;
use Okay\Core\ServiceLocator;
use Okay\Entities\OrdersEntity;
use Okay\Entities\OrderStatusEntity;
use Okay\Entities\PurchasesEntity;
use Okay\Entities\ProductsEntity;
use Okay\Entities\BrandsEntity;

/** Допоміжна логіка експорту замовлень у CSV. */
class BackendOrdersExportHelper
{
    private OrdersEntity $ordersEntity;
    private PurchasesEntity $purchasesEntity;
    private OrderStatusEntity $orderStatusEntity;
    private ProductsEntity $productsEntity;
    private BrandsEntity $brandsEntity;
    private Request $request;
    private Settings $settings;
    private EntityFactory $entityFactory;
    private Modules $modules;

    public function __construct(EntityFactory $entityFactory, Request $request, Settings $settings, Modules $modules)
    {
        $this->ordersEntity = $entityFactory->get(OrdersEntity::class);
        $this->purchasesEntity = $entityFactory->get(PurchasesEntity::class);
        $this->orderStatusEntity = $entityFactory->get(OrderStatusEntity::class);
        $this->productsEntity = $entityFactory->get(ProductsEntity::class);
        $this->brandsEntity = $entityFactory->get(BrandsEntity::class);
        $this->request = $request;
        $this->settings = $settings;
        $this->entityFactory = $entityFactory;
        $this->modules = $modules;
    }

    /** Повертає назви колонок для експорту. */
    public function getColumnsNames()
    {
        $columnsNames = [
            'order_id' => 'Номер замовлення',
            'multiple_items' => 'Кілька позицій',
            'status' => 'Статус',
            'brand' => 'Бренд',
            'sku' => 'SKU товару',
            'product_name' => 'Назва товару',
            'quantity' => 'Кількість',
            'price' => 'Ціна',
            'date' => 'Дата замовлення',
        ];

        if ($this->modules->isActiveModule('Sviat', 'OrderCancellationReason')) {
            $columnsNamesWithCancellation = [];
            foreach ($columnsNames as $key => $value) {
                $columnsNamesWithCancellation[$key] = $value;
                if ($key === 'status') {
                    $columnsNamesWithCancellation['cancellation_reason'] = 'Причина скасування';
                }
            }
            $columnsNames = $columnsNamesWithCancellation;
        }

        $exportTtn = $this->exportParam('export_ttn');
        if (($exportTtn == '1' || $exportTtn == '2' || $exportTtn === 1 || $exportTtn === 2)
            && $this->modules->isActiveModule('Sviat', 'NovaPoshtaTracking')
        ) {
            $columnsNamesWithTtn = [];
            foreach ($columnsNames as $key => $value) {
                $columnsNamesWithTtn[$key] = $value;
                if ($key === 'status') {
                    $columnsNamesWithTtn['ttn'] = 'ТТН доставки';
                }
            }
            $columnsNames = $columnsNamesWithTtn;
        }

        return ExtenderFacade::execute(__METHOD__, $columnsNames, func_get_args());
    }

    /** Повертає параметри експорту. */
    public function getConfigParams()
    {
        $columnDelimiter = (string) $this->settings->get('sviat__orders_export__column_delimiter');
        if (!in_array($columnDelimiter, [';', ',', "\t"], true)) {
            $columnDelimiter = ';';
        }

        $ordersCount = (int) $this->settings->get('sviat__orders_export__orders_count');
        if ($ordersCount <= 0) {
            $ordersCount = 100;
        }

        $params = (object) [
            'column_delimiter' => $columnDelimiter,
            'orders_count' => $ordersCount,
            'export_files_dir' => 'backend/files/export/',
            'filename' => 'export_orders_enhanced.csv',
        ];

        return ExtenderFacade::execute(__METHOD__, $params, func_get_args());
    }

    /** Готує експорт і збирає фільтри. */
    public function setUp($exportFilesDir, $filename, &$columnsNames, $columnDelimiter, $ordersCount)
    {
        session_write_close();
        unset($_SESSION['lang_id']);
        unset($_SESSION['admin_lang_id']);

        $page = $this->exportParam('page');
        if (empty($page) || $page == 1) {
            $page = 1;
            if (is_writable($exportFilesDir . $filename)) {
                unlink($exportFilesDir . $filename);
            }
        }

        $f = fopen($exportFilesDir . $filename, 'ab');

        $filter = ['page' => $page, 'limit' => $ordersCount];

        $statusId = $this->exportParam('status', 'integer');
        if (!empty($statusId)) {
            $filter['status_id'] = $statusId;
        }

        $labelId = $this->exportParam('label', 'integer');
        if (!empty($labelId)) {
            $filter['label'] = $labelId;
        }

        $fromDate = $this->exportParam('from_date');
        $toDate = $this->exportParam('to_date');

        if (!empty($fromDate)) {
            $filter['from_date'] = $fromDate;
        }
        if (!empty($toDate)) {
            $filter['to_date'] = $toDate;
        }

        $exportTtn = $this->exportParam('export_ttn');
        if (($exportTtn == '2' || $exportTtn === 2)
            && $this->modules->isActiveModule('Sviat', 'NovaPoshtaTracking')
        ) {
            $filter['has_ttn'] = true;
        }

        $brandIds = $this->normalizeBrandIds($this->exportParam('brand_ids'));
        $filter = $this->appendBrandOrdersFilter($filter, $brandIds);

        if ($page == 1) {
            fputcsv($f, $columnsNames, $columnDelimiter, '"', '\\');
        }

        fclose($f);
        return ExtenderFacade::execute(__METHOD__, [$filter, $page], func_get_args());
    }

    /** Завантажує замовлення за фільтром. */
    public function fetchOrders($filter)
    {
        $orders = $this->ordersEntity->mappedBy('id')->find($filter);

        if ($this->modules->isActiveModule('Sviat', 'OrderCancellationReason')) {
            try {
                $ocrHelper = ServiceLocator::getInstance()->getService(
                    \Okay\Modules\Sviat\OrderCancellationReason\Backend\Helpers\BackendOrderCancellationReasonHelper::class
                );
                $cancelledId = $ocrHelper->getCancelledStatusId();
                if ($cancelledId !== null) {
                    foreach ($orders as $order) {
                        if ((int) $order->status_id === $cancelledId
                            && (!empty($order->cancellation_reason_id) || trim((string) ($order->cancellation_reason_text ?? '')) !== '')
                        ) {
                            $order->cancellation_reason_display = $ocrHelper->getCancellationReasonDisplayText($order);
                        }
                    }
                }
            } catch (\Throwable $e) {
                // Ігноруємо, якщо модуль недоступний.
            }
        }

        return ExtenderFacade::execute(__METHOD__, $orders, func_get_args());
    }

    /** Групує покупки за замовленнями. */
    public function attachPurchases($orders)
    {
        $ordersIds = [];
        foreach ($orders as $order) {
            $ordersIds[] = $order->id;
        }

        $purchases = [];
        if (!empty($ordersIds)) {
            // noLimit(): дефолтний select-ліміт 100 записів обрізає покупки на сторінках з багатьма замовленнями.
            $purchasesData = $this->purchasesEntity->noLimit()->find(['order_id' => $ordersIds]);

            $brandIds = $this->normalizeBrandIds($this->exportParam('brand_ids'));
            if (!empty($brandIds)) {
                $productIds = [];
                foreach ($purchasesData as $purchase) {
                    if (!empty($purchase->product_id)) {
                        $productIds[] = (int) $purchase->product_id;
                    }
                }

                $allowedProductIds = [];
                if (!empty($productIds)) {
                    $products = $this->productsEntity->noLimit()->cols(['id', 'brand_id'])->find(['id' => array_unique($productIds)]);
                    foreach ($products as $product) {
                        if (in_array((int) $product->brand_id, $brandIds, true)) {
                            $allowedProductIds[(int) $product->id] = true;
                        }
                    }
                }

                $filteredPurchases = [];
                foreach ($purchasesData as $purchase) {
                    $productId = !empty($purchase->product_id) ? (int) $purchase->product_id : null;
                    if ($productId !== null && isset($allowedProductIds[$productId])) {
                        $filteredPurchases[] = $purchase;
                    }
                }
                $purchasesData = $filteredPurchases;
            }

            foreach ($purchasesData as $purchase) {
                $purchases[$purchase->order_id][] = $purchase;
            }
        }

        return ExtenderFacade::execute(__METHOD__, $purchases, func_get_args());
    }

    /** Повертає статуси для замовлень. */
    public function attachStatuses($orders)
    {
        $statusesIds = [];
        foreach ($orders as $order) {
            if (!empty($order->status_id)) {
                $statusesIds[] = $order->status_id;
            }
        }

        $statuses = [];
        if (!empty($statusesIds)) {
            $statusesData = $this->orderStatusEntity->noLimit()->find(['id' => array_unique($statusesIds)]);
            foreach ($statusesData as $status) {
                $statuses[$status->id] = $status;
            }
        }

        return ExtenderFacade::execute(__METHOD__, $statuses, func_get_args());
    }

    /** Повертає ТТН для замовлень. */
    public function attachTtn($orders)
    {
        $ttnData = [];

        if (!$this->modules->isActiveModule('Sviat', 'NovaPoshtaTracking')) {
            return ExtenderFacade::execute(__METHOD__, $ttnData, func_get_args());
        }

        $ordersIds = [];
        foreach ($orders as $order) {
            $ordersIds[] = $order->id;
        }

        if (!empty($ordersIds)) {
            try {
                $novaPoshtaTrackingEntity = $this->entityFactory->get('Okay\Modules\Sviat\NovaPoshtaTracking\Entities\NovaPoshtaTrackingEntity');
                // noLimit(): дефолтний ліміт + defaultOrderFields=id DESC викидає ТТН старих замовлень при великих сторінках.
                $trackingData = $novaPoshtaTrackingEntity->noLimit()->find(['order_id' => $ordersIds]);

                foreach ($trackingData as $tracking) {
                    if (!empty($tracking->int_doc_number)) {
                        $ttnData[$tracking->order_id] = $tracking->int_doc_number;
                    }
                }
            } catch (\Exception $e) {
                // Ігноруємо, якщо модуль або entity недоступні.
            }
        }

        return ExtenderFacade::execute(__METHOD__, $ttnData, func_get_args());
    }

    /** Повертає назви брендів для товарів у покупках. */
    public function attachBrands($purchases)
    {
        $brandsData = [];

        $productIds = [];
        foreach ($purchases as $orderPurchases) {
            foreach ($orderPurchases as $purchase) {
                if (!empty($purchase->product_id)) {
                    $productIds[] = $purchase->product_id;
                }
            }
        }

        if (!empty($productIds)) {
            $products = $this->productsEntity->noLimit()->cols(['id', 'brand_id'])->find(['id' => array_unique($productIds)]);

            $brandIds = [];
            $productBrandMap = [];
            foreach ($products as $product) {
                if (!empty($product->brand_id)) {
                    $brandIds[] = $product->brand_id;
                    $productBrandMap[$product->id] = $product->brand_id;
                }
            }

            if (!empty($brandIds)) {
                $brands = $this->brandsEntity->noLimit()->cols(['id', 'name'])->mappedBy('id')->find(['id' => array_unique($brandIds)]);

                foreach ($purchases as $orderPurchases) {
                    foreach ($orderPurchases as $purchase) {
                        if (!empty($purchase->product_id) && isset($productBrandMap[$purchase->product_id])) {
                            $brandId = $productBrandMap[$purchase->product_id];
                            if (isset($brands[$brandId])) {
                                $brandsData[$purchase->product_id] = $brands[$brandId]->name;
                            }
                        }
                    }
                }
            }
        }

        return ExtenderFacade::execute(__METHOD__, $brandsData, func_get_args());
    }

    /**
     * Записує поточну порцію замовлень у CSV.
     *
     * Колонка без свого case бере значення з однойменного поля замовлення. Так
     * сусідній модуль додає свою колонку самим лише extender'ом на
     * getColumnsNames(), не чіпаючи цей метод.
     */
    public function exportRun($exportFilesDir, $filename, $orders, $purchases, $statuses, $filter, $columnsNames, $columnDelimiter, $ordersCount, $page)
    {
        $f = fopen($exportFilesDir . $filename, 'ab');

        $includeTtn = isset($columnsNames['ttn']);

        $ttnData = [];
        if ($includeTtn) {
            $ttnData = $this->attachTtn($orders);
        }

        $brandsData = $this->attachBrands($purchases);

        foreach ($orders as $order) {
            $orderStatusName = '';
            if (!empty($order->status_id)) {
                if (!empty($order->status_name)) {
                    $orderStatusName = $order->status_name;
                } elseif (isset($statuses[$order->status_id])) {
                    $orderStatusName = $statuses[$order->status_id]->name;
                }
            }

            $orderTtn = $includeTtn && isset($ttnData[$order->id]) ? $ttnData[$order->id] : '';
            $orderPurchases = isset($purchases[$order->id]) ? $purchases[$order->id] : [];
            $hasMultipleItems = count($orderPurchases) > 1;
            $multipleItemsValue = $hasMultipleItems ? $order->id : '';

            if (empty($orderPurchases)) {
                $row = [];
                foreach ($columnsNames as $key => $columnName) {
                    switch ($key) {
                        case 'order_id':
                            $row[] = $order->id;
                            break;
                        case 'multiple_items':
                            $row[] = '';
                            break;
                        case 'status':
                            $row[] = $orderStatusName;
                            break;
                        case 'cancellation_reason':
                            $row[] = $order->cancellation_reason_display ?? '';
                            break;
                        case 'ttn':
                            $row[] = $orderTtn;
                            break;
                        case 'sku':
                            $row[] = '';
                            break;
                        case 'product_name':
                            $row[] = '';
                            break;
                        case 'quantity':
                            $row[] = '';
                            break;
                        case 'price':
                            $row[] = '';
                            break;
                        case 'brand':
                            $row[] = '';
                            break;
                        case 'date':
                            $row[] = $order->date;
                            break;
                        default:
                            $row[] = $order->$key ?? '';
                    }
                }
                fputcsv($f, $row, $columnDelimiter, '"', '\\');
            } else {
                foreach ($orderPurchases as $purchase) {
                    $productName = $purchase->product_name;
                    if (!empty($purchase->variant_name)) {
                        $productName .= ' (' . $purchase->variant_name . ')';
                    }

                    $brandName = '';
                    if (!empty($purchase->product_id) && isset($brandsData[$purchase->product_id])) {
                        $brandName = $brandsData[$purchase->product_id];
                    }

                    $row = [];
                    foreach ($columnsNames as $key => $columnName) {
                        switch ($key) {
                            case 'order_id':
                                $row[] = $order->id;
                                break;
                            case 'multiple_items':
                                $row[] = $multipleItemsValue;
                                break;
                            case 'status':
                                $row[] = $orderStatusName;
                                break;
                            case 'cancellation_reason':
                                $row[] = $order->cancellation_reason_display ?? '';
                                break;
                            case 'ttn':
                                $row[] = $orderTtn;
                                break;
                            case 'sku':
                                $row[] = $purchase->sku ?? '';
                                break;
                            case 'product_name':
                                $row[] = $productName;
                                break;
                            case 'quantity':
                                $row[] = $purchase->amount ?? '';
                                break;
                            case 'price':
                                $row[] = $purchase->price ?? '';
                                break;
                            case 'brand':
                                $row[] = $brandName;
                                break;
                            case 'date':
                                $row[] = $order->date;
                                break;
                            default:
                                $row[] = $order->$key ?? '';
                        }
                    }
                    fputcsv($f, $row, $columnDelimiter, '"', '\\');
                }
            }
        }

        $totalOrders = $this->ordersEntity->count($filter);
        fclose($f);

        if ($ordersCount * $page < $totalOrders) {
            return ['end' => false, 'page' => $page, 'totalpages' => ceil($totalOrders / $ordersCount)];
        }

        $data = ['end' => true, 'page' => $page, 'totalpages' => ceil($totalOrders / $ordersCount)];

        mb_substitute_character('none');
        file_put_contents(
            $exportFilesDir . $filename,
            mb_convert_encoding(file_get_contents($exportFilesDir . $filename), 'Windows-1251')
        );

        return $data;
    }

    /** Повертає коректний список ID брендів. */
    private function normalizeBrandIds($rawBrandIds): array
    {
        if (!is_array($rawBrandIds)) {
            return [];
        }

        $brandIds = [];
        foreach ($rawBrandIds as $rawBrandId) {
            $brandId = (int) $rawBrandId;
            if ($brandId > 0) {
                $brandIds[$brandId] = $brandId;
            }
        }

        return array_values($brandIds);
    }

    /**
     * Параметри фільтрації експорту: спочатку POST (не губляться при nginx-редиректі без query string), інакше GET.
     */
    private function exportParam(string $name, ?string $type = null, $default = null)
    {
        if ($this->request->method('post')) {
            return $this->request->post($name, $type, $default);
        }

        return $this->request->get($name, $type, $default);
    }

    private function appendBrandOrdersFilter(array $filter, array $brandIds): array
    {
        if (empty($brandIds)) {
            return $filter;
        }

        $productIds = $this->productsEntity->noLimit()->cols(['id'])->find(['brand_id' => $brandIds]);
        if (empty($productIds)) {
            $filter['id'] = [-1];
            return $filter;
        }

        $normalizedProductIds = [];
        foreach ($productIds as $productId) {
            $productId = (int) $productId;
            if ($productId > 0) {
                $normalizedProductIds[$productId] = $productId;
            }
        }

        if (empty($normalizedProductIds)) {
            $filter['id'] = [-1];
            return $filter;
        }

        $purchaseOrderIds = $this->purchasesEntity->noLimit()->cols(['order_id'])->find([
            'product_id' => array_values($normalizedProductIds),
        ]);

        if (empty($purchaseOrderIds)) {
            $filter['id'] = [-1];
            return $filter;
        }

        $orderIds = [];
        foreach ($purchaseOrderIds as $orderId) {
            $orderId = (int) $orderId;
            if ($orderId > 0) {
                $orderIds[$orderId] = $orderId;
            }
        }

        $filter['id'] = !empty($orderIds) ? array_values($orderIds) : [-1];

        return $filter;
    }
}
