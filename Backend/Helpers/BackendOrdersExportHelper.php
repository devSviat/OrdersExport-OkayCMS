<?php


namespace Okay\Modules\Sviat\OrdersExport\Backend\Helpers;

use Okay\Core\EntityFactory;
use Okay\Core\Modules\Extender\ExtenderFacade;
use Okay\Core\Modules\Modules;
use Okay\Core\Request;
use Okay\Entities\OrdersEntity;
use Okay\Entities\OrderStatusEntity;
use Okay\Entities\PurchasesEntity;
use Okay\Entities\ProductsEntity;
use Okay\Entities\BrandsEntity;

/**
 * Helper для експорту замовлень у CSV формат
 */
class BackendOrdersExportHelper
{
    private OrdersEntity $ordersEntity;
    private PurchasesEntity $purchasesEntity;
    private OrderStatusEntity $orderStatusEntity;
    private ProductsEntity $productsEntity;
    private BrandsEntity $brandsEntity;
    private Request $request;
    private EntityFactory $entityFactory;
    private Modules $modules;

    /**
     * @param EntityFactory $entityFactory
     * @param Request $request
     * @param Modules $modules
     */
    public function __construct(EntityFactory $entityFactory, Request $request, Modules $modules)
    {
        $this->ordersEntity = $entityFactory->get(OrdersEntity::class);
        $this->purchasesEntity = $entityFactory->get(PurchasesEntity::class);
        $this->orderStatusEntity = $entityFactory->get(OrderStatusEntity::class);
        $this->productsEntity = $entityFactory->get(ProductsEntity::class);
        $this->brandsEntity = $entityFactory->get(BrandsEntity::class);
        $this->request = $request;
        $this->entityFactory = $entityFactory;
        $this->modules = $modules;
    }

    /**
     * Отримує список назв колонок для експорту
     * 
     * @return array<string, string> Масив з ключами колонок та їх назвами
     */
    public function getColumnsNames()
    {
        $columnsNames = [
            'order_id' => 'Номер замовлення',
            'multiple_items' => 'Позицій',
            'status' => 'Статус',
            'brand' => 'Бренд',
            'sku' => 'SKU товару',
            'product_name' => 'Назва товару',
            'quantity' => 'Кількість',
            'price' => 'Ціна',
            'date' => 'Дата замовлення',
        ];

        $exportTtn = $this->request->get('export_ttn');
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

    /**
     * Отримує параметри конфігурації експорту
     * 
     * @return object Об'єкт з параметрами: column_delimiter, orders_count, export_files_dir, filename
     */
    public function getConfigParams()
    {
        $params = (object) [
            'column_delimiter' => ';',
            'orders_count' => 100,
            'export_files_dir' => 'backend/files/export/',
            'filename' => 'export_orders_enhanced.csv',
        ];

        return ExtenderFacade::execute(__METHOD__, $params, func_get_args());
    }

    /**
     * Налаштовує параметри експорту та формує фільтри
     * 
     * @param string $exportFilesDir Директорія для збереження файлу експорту
     * @param string $filename Ім'я файлу експорту
     * @param array &$columnsNames Масив назв колонок (модифікується)
     * @param string $columnDelimiter Роздільник колонок
     * @param int $ordersCount Кількість замовлень на сторінку
     * @return array{0: array, 1: int} Масив з фільтрами та номером сторінки
     */
    public function setUp($exportFilesDir, $filename, &$columnsNames, $columnDelimiter, $ordersCount)
    {
        session_write_close();
        unset($_SESSION['lang_id']);
        unset($_SESSION['admin_lang_id']);

        $page = $this->request->get('page');
        if (empty($page) || $page == 1) {
            $page = 1;
            if (is_writable($exportFilesDir . $filename)) {
                unlink($exportFilesDir . $filename);
            }
        }

        $f = fopen($exportFilesDir . $filename, 'ab');

        $filter = ['page' => $page, 'limit' => $ordersCount];

        $statusId = $this->request->get('status', 'integer');
        if (!empty($statusId)) {
            $filter['status_id'] = $statusId;
        }

        $labelId = $this->request->get('label', 'integer');
        if (!empty($labelId)) {
            $filter['label'] = $labelId;
        }

        $fromDate = $this->request->get('from_date');
        $toDate = $this->request->get('to_date');

        if (!empty($fromDate)) {
            $filter['from_date'] = $fromDate;
        }
        if (!empty($toDate)) {
            $filter['to_date'] = $toDate;
        }

        $exportTtn = $this->request->get('export_ttn');
        if (($exportTtn == '2' || $exportTtn === 2)
            && $this->modules->isActiveModule('Sviat', 'NovaPoshtaTracking')
        ) {
            $filter['has_ttn'] = true;
        }

        if ($page == 1) {
            fputcsv($f, $columnsNames, $columnDelimiter);
        }

        fclose($f);
        return ExtenderFacade::execute(__METHOD__, [$filter, $page], func_get_args());
    }

    /**
     * Отримує замовлення згідно з фільтрами
     * 
     * @param array $filter Масив фільтрів для пошуку замовлень
     * @return array<int, object> Масив замовлень, індексований по ID
     */
    public function fetchOrders($filter)
    {
        $exportTtn = $this->request->get('export_ttn');
        $filterByTtn = ($exportTtn == '2' || $exportTtn === 2)
            && $this->modules->isActiveModule('Sviat', 'NovaPoshtaTracking');

        $orders = $this->ordersEntity->mappedBy('id')->find($filter);

        if ($filterByTtn) {
            try {
                $novaPoshtaTrackingEntity = $this->entityFactory->get('Okay\Modules\Sviat\NovaPoshtaTracking\Entities\NovaPoshtaTrackingEntity');
                $ordersIds = array_keys($orders);

                if (!empty($ordersIds)) {
                    $trackingData = $novaPoshtaTrackingEntity->find(['order_id' => $ordersIds]);
                    $ordersWithTtn = [];
                    foreach ($trackingData as $tracking) {
                        if (!empty($tracking->int_doc_number) && !empty($tracking->order_id)) {
                            $ordersWithTtn[$tracking->order_id] = true;
                        }
                    }

                    $filteredOrders = [];
                    foreach ($orders as $orderId => $order) {
                        if (isset($ordersWithTtn[$orderId])) {
                            $filteredOrders[$orderId] = $order;
                        }
                    }
                    $orders = $filteredOrders;
                } else {
                    $orders = [];
                }
            } catch (\Exception $e) {
                $orders = [];
            }
        }

        foreach ($orders as $order) {
            if (empty($order->status_name) && !empty($order->status_id)) {
                $status = $this->orderStatusEntity->get($order->status_id);
                if ($status) {
                    $order->status_name = $status->name;
                }
            }
        }
        return ExtenderFacade::execute(__METHOD__, $orders, func_get_args());
    }

    /**
     * Прикріплює покупки до замовлень
     * 
     * @param array<int, object> $orders Масив замовлень
     * @return array<int, array<object>> Масив покупок, згрупованих по order_id
     */
    public function attachPurchases($orders)
    {
        $ordersIds = [];
        foreach ($orders as $order) {
            $ordersIds[] = $order->id;
        }

        $purchases = [];
        if (!empty($ordersIds)) {
            $purchasesData = $this->purchasesEntity->find(['order_id' => $ordersIds]);
            foreach ($purchasesData as $purchase) {
                $purchases[$purchase->order_id][] = $purchase;
            }
        }

        return ExtenderFacade::execute(__METHOD__, $purchases, func_get_args());
    }

    /**
     * Прикріплює статуси до замовлень
     * 
     * @param array<int, object> $orders Масив замовлень
     * @return array<int, object> Масив статусів, індексований по ID
     */
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
            $statusesData = $this->orderStatusEntity->find(['id' => array_unique($statusesIds)]);
            foreach ($statusesData as $status) {
                $statuses[$status->id] = $status;
            }
        }

        return ExtenderFacade::execute(__METHOD__, $statuses, func_get_args());
    }

    /**
     * Прикріплює номери ТТН до замовлень
     * 
     * @param array<int, object> $orders Масив замовлень
     * @return array<int, string> Масив номерів ТТН, індексований по order_id
     */
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
                $trackingData = $novaPoshtaTrackingEntity->find(['order_id' => $ordersIds]);

                foreach ($trackingData as $tracking) {
                    if (!empty($tracking->int_doc_number)) {
                        $ttnData[$tracking->order_id] = $tracking->int_doc_number;
                    }
                }
            } catch (\Exception $e) {
                // Модуль не встановлений або entity недоступний
            }
        }

        return ExtenderFacade::execute(__METHOD__, $ttnData, func_get_args());
    }

    /**
     * Прикріплює назви брендів до покупок
     * 
     * @param array<int, array<object>> $purchases Масив покупок, згрупованих по order_id
     * @return array<int, string> Масив назв брендів, індексований по product_id
     */
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
            $products = $this->productsEntity->cols(['id', 'brand_id'])->find(['id' => array_unique($productIds)]);

            $brandIds = [];
            $productBrandMap = [];
            foreach ($products as $product) {
                if (!empty($product->brand_id)) {
                    $brandIds[] = $product->brand_id;
                    $productBrandMap[$product->id] = $product->brand_id;
                }
            }

            if (!empty($brandIds)) {
                $brands = $this->brandsEntity->cols(['id', 'name'])->mappedBy('id')->find(['id' => array_unique($brandIds)]);

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
     * Виконує експорт замовлень у CSV файл
     * 
     * @param string $exportFilesDir Директорія для збереження файлу
     * @param string $filename Ім'я файлу
     * @param array<int, object> $orders Масив замовлень
     * @param array<int, array<object>> $purchases Масив покупок
     * @param array<int, object> $statuses Масив статусів
     * @param array $filter Масив фільтрів
     * @param array<string, string> $columnsNames Масив назв колонок
     * @param string $columnDelimiter Роздільник колонок
     * @param int $ordersCount Кількість замовлень на сторінку
     * @param int $page Номер поточної сторінки
     * @return array<string, mixed>|null Масив з інформацією про завершення експорту або null
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
            $purchasesCount = count($orderPurchases);
            // Якщо 1 позиція - порожній рядок, якщо декілька - кількість позицій
            $multipleItemsValue = ($purchasesCount > 1) ? $purchasesCount : '';

            if (empty($orderPurchases)) {
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
                            $row[] = '';
                    }
                }
                fputcsv($f, $row, $columnDelimiter);
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
                                $row[] = '';
                        }
                    }
                    fputcsv($f, $row, $columnDelimiter);
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
}
