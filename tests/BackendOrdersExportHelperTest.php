<?php

namespace Modules\Sviat\OrdersExport;

use Okay\Core\EntityFactory;
use Okay\Core\Modules\Modules;
use Okay\Core\Request;
use Okay\Core\Settings;
use Okay\Entities\BrandsEntity;
use Okay\Entities\OrderStatusEntity;
use Okay\Entities\ProductsEntity;
use Okay\Entities\PurchasesEntity;
use Okay\Modules\Sviat\NovaPoshtaTracking\Entities\NovaPoshtaTrackingEntity;
use Okay\Modules\Sviat\OrdersExport\Backend\Helpers\BackendOrdersExportHelper;
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/RequestStub.php';

// Сусідній модуль NovaPoshtaTracking необов'язковий і в прогоні релізу поруч
// його немає — без класу createMock() не має що дублювати.
if (!class_exists(NovaPoshtaTrackingEntity::class)) {
    require_once __DIR__ . '/NovaPoshtaTrackingEntityStub.php';
}

/**
 * Перевіряє, що допоміжні методи експорту не втрачають дані через дефолтний ліміт 100 у Entity.
 *
 * Дефолтний select-ліміт у Okay\Core\Entity\filter:17 = 100 записів. Без noLimit() кожен
 * виклик find() обрізає вибірку. Для експорту замовлень це призводить до пропадання покупок,
 * брендів та ТТН для рядків CSV, особливо коли orders_count наближається до 1000 (макс. з налаштувань).
 */
class BackendOrdersExportHelperTest extends TestCase
{
    private const DEFAULT_SELECT_LIMIT = 100;

    public function testAttachPurchasesReturnsAllPurchasesEvenWhenAboveDefaultLimit(): void
    {
        $orders = $this->buildOrders(60);
        $purchases = $this->buildPurchases($orders, 3); // 180 покупок

        $helper = $this->buildHelper([
            PurchasesEntity::class => $this->buildEntityMock(PurchasesEntity::class, $purchases, ['order_id']),
        ]);

        $result = $helper->attachPurchases($orders);

        $totalAttached = array_sum(array_map('count', $result));
        $this->assertSame(
            count($purchases),
            $totalAttached,
            'Усі покупки мають потрапити у згрупований результат — інакше у CSV буде "Кілька позицій" з пропусками рядків.'
        );
    }

    public function testAttachPurchasesAttachesPurchasesToLastOrdersWhenManyOrdersOnOnePage(): void
    {
        $orders = $this->buildOrders(150);
        $purchases = $this->buildPurchases($orders, 1); // 150 покупок

        $helper = $this->buildHelper([
            PurchasesEntity::class => $this->buildEntityMock(PurchasesEntity::class, $purchases, ['order_id']),
        ]);

        $result = $helper->attachPurchases($orders);

        // Останні замовлення (101..150) без noLimit() залишаються без покупок.
        $lastOrderId = $orders[149]->id;
        $this->assertArrayHasKey(
            $lastOrderId,
            $result,
            'Покупки для останніх замовлень сторінки мають бути прикріплені (без noLimit() вони губляться).'
        );
    }

    public function testAttachTtnReturnsTtnForAllOrdersAboveDefaultLimit(): void
    {
        $orders = $this->buildOrders(150);
        $trackings = $this->buildTrackings($orders);

        $helper = $this->buildHelper([
            NovaPoshtaTrackingEntity::class => $this->buildEntityMock(NovaPoshtaTrackingEntity::class, $trackings, ['order_id']),
        ], [
            'NovaPoshtaTracking' => true,
        ]);

        $result = $helper->attachTtn($orders);

        $this->assertCount(
            count($orders),
            $result,
            'Кожне замовлення має отримати ТТН — без noLimit() для частини старих замовлень ТТН пропадає (defaultOrderFields = id DESC).'
        );
    }

    public function testAttachTtnIsEmptyWhenNovaPoshtaTrackingDisabled(): void
    {
        $orders = $this->buildOrders(5);

        $helper = $this->buildHelper([], ['NovaPoshtaTracking' => false]);

        $this->assertSame([], $helper->attachTtn($orders));
    }

    public function testAttachBrandsResolvesBrandsForMoreThanDefaultLimitProducts(): void
    {
        $purchases = [];
        for ($orderId = 1; $orderId <= 60; $orderId++) {
            $purchasesPerOrder = [];
            for ($i = 0; $i < 3; $i++) {
                $productId = $orderId * 10 + $i; // 60×3 = 180 унікальних product_id
                $purchasesPerOrder[] = (object) [
                    'order_id' => $orderId,
                    'product_id' => $productId,
                ];
            }
            $purchases[$orderId] = $purchasesPerOrder;
        }

        $productsMap = [];
        foreach ($purchases as $orderPurchases) {
            foreach ($orderPurchases as $purchase) {
                $productsMap[$purchase->product_id] = (object) [
                    'id' => $purchase->product_id,
                    'brand_id' => 1,
                ];
            }
        }
        $products = array_values($productsMap);
        $brands = [(object) ['id' => 1, 'name' => 'Test brand']];

        $helper = $this->buildHelper([
            ProductsEntity::class => $this->buildEntityMock(ProductsEntity::class, $products, ['id']),
            BrandsEntity::class => $this->buildEntityMock(BrandsEntity::class, $brands, ['id'], 'id'),
        ]);

        $result = $helper->attachBrands($purchases);

        $this->assertCount(
            count($products),
            $result,
            'Кожен унікальний product_id має отримати назву бренда; без noLimit() при >100 продуктах деякі рядки матимуть порожнє поле "brand".'
        );
    }

    public function testAttachStatusesReturnsAllStatusesAboveDefaultLimit(): void
    {
        $orders = [];
        $statusList = [];
        for ($i = 1; $i <= 120; $i++) {
            $orders[] = (object) ['id' => $i, 'status_id' => $i];
            $statusList[] = (object) ['id' => $i, 'name' => 'Status ' . $i];
        }

        $helper = $this->buildHelper([
            OrderStatusEntity::class => $this->buildEntityMock(OrderStatusEntity::class, $statusList, ['id']),
        ]);

        $result = $helper->attachStatuses($orders);

        $this->assertCount(
            count($statusList),
            $result,
            'Усі статуси, що зустрічаються у замовленнях, мають резолвитись; без noLimit() лишається лише 100.'
        );
    }

    public function testAttachPurchasesWithBrandFilterFindsMatchingPurchasesBeyondDefaultLimit(): void
    {
        $orders = $this->buildOrders(80);
        $purchases = [];
        $brandedProductId = 999;
        $otherProductId = 1;
        // 80 замовлень × 2 покупки = 160 покупок. Перші 100 - "інший" бренд, останні 60 - "наш" бренд.
        $position = 0;
        foreach ($orders as $order) {
            for ($i = 0; $i < 2; $i++) {
                $purchases[] = (object) [
                    'order_id' => $order->id,
                    'product_id' => ($position < 100) ? $otherProductId : $brandedProductId,
                ];
                $position++;
            }
        }

        $products = [
            (object) ['id' => $brandedProductId, 'brand_id' => 7],
            (object) ['id' => $otherProductId, 'brand_id' => 8],
        ];

        $request = new RequestStub(true, function ($name) {
            return $name === 'brand_ids' ? [7] : null;
        });

        $helper = $this->buildHelper([
            PurchasesEntity::class => $this->buildEntityMock(PurchasesEntity::class, $purchases, ['order_id']),
            ProductsEntity::class => $this->buildEntityMock(ProductsEntity::class, $products, ['id']),
        ], [], $request);

        $result = $helper->attachPurchases($orders);

        $totalAttached = array_sum(array_map('count', $result));
        $this->assertGreaterThan(
            0,
            $totalAttached,
            'Фільтр по бренду має знаходити покупки, що йдуть після перших 100 у вибірці — без noLimit() їх не видно.'
        );
    }

    /**
     * Створює тестовий BackendOrdersExportHelper з мокнутими залежностями.
     *
     * @param array<class-string|string, object> $entityMocks
     * @param array<string, bool> $activeModules мапа короткої назви модуля → активний
     */
    private function buildHelper(array $entityMocks, array $activeModules = [], ?Request $request = null): BackendOrdersExportHelper
    {
        $factory = $this->createMock(EntityFactory::class);
        $factory->method('get')->willReturnCallback(function (string $class) use ($entityMocks) {
            if (isset($entityMocks[$class])) {
                return $entityMocks[$class];
            }
            // Дефолтний "пустий" мок для класів, які лише инстансиются у конструкторі, але не використовуються тестом.
            return $this->buildEntityMock($class, [], []);
        });

        if ($request === null) {
            // PHPUnit 13 не дублює класи з методом на імʼя "method" — беремо ручний стаб.
            $request = new RequestStub(true);
        }

        $settings = $this->createMock(Settings::class);
        $settings->method('get')->willReturn(null);

        $modules = $this->createMock(Modules::class);
        $modules->method('isActiveModule')->willReturnCallback(
            static fn ($_vendor, string $name) => $activeModules[$name] ?? false
        );

        return new BackendOrdersExportHelper($factory, $request, $settings, $modules);
    }

    /**
     * @return list<\stdClass>
     */
    private function buildOrders(int $count): array
    {
        $orders = [];
        for ($i = 1; $i <= $count; $i++) {
            $orders[] = (object) [
                'id' => $i,
                'status_id' => 1,
                'date' => '2026-05-25',
            ];
        }
        return $orders;
    }

    /**
     * @param list<\stdClass> $orders
     * @return list<\stdClass>
     */
    private function buildPurchases(array $orders, int $perOrder): array
    {
        $purchases = [];
        foreach ($orders as $order) {
            for ($i = 0; $i < $perOrder; $i++) {
                $purchases[] = (object) [
                    'order_id' => $order->id,
                    'product_id' => $order->id * 100 + $i,
                    'product_name' => 'Item ' . $order->id . '/' . $i,
                    'amount' => 1,
                    'price' => 50.0,
                ];
            }
        }
        return $purchases;
    }

    /**
     * @param list<\stdClass> $orders
     * @return list<\stdClass>
     */
    private function buildTrackings(array $orders): array
    {
        $trackings = [];
        foreach ($orders as $i => $order) {
            $trackings[] = (object) [
                'id' => $i + 1,
                'order_id' => $order->id,
                'int_doc_number' => '202' . str_pad((string) $order->id, 8, '0', STR_PAD_LEFT),
            ];
        }
        // Імітує defaultOrderFields = ['id DESC'] у NovaPoshtaTrackingEntity:
        // без noLimit() будуть лишатись лише найновіші 100 — старі замовлення втрачають ТТН.
        usort($trackings, static fn ($a, $b) => $b->id <=> $a->id);
        return $trackings;
    }

    /**
     * Створює PHPUnit-мок Entity-нащадка, який імітує дефолтний ліміт 100 у Okay\Core\Entity:
     * - find() застосовує фільтр по вказаних полях,
     * - якщо noLimit() не викликали — обрізає результат до 100 записів,
     * - cols() та mappedBy() повертають $this (як у CRUD/Entity),
     * - $defaultMappedByField — імітує виклик mappedBy у production-коді (наприклад, brandsEntity->mappedBy('id')).
     *
     * @template T of object
     * @param class-string<T> $entityClass
     * @param list<\stdClass> $records
     * @param list<string> $filterableFields
     * @return T
     */
    private function buildEntityMock(string $entityClass, array $records, array $filterableFields, ?string $defaultMappedByField = null)
    {
        $mock = $this->createMock($entityClass);

        // mappedBy() — final у базовому Entity, його не можна перевизначити createMock.
        // Він безпечний при $defaultMappedByField=null (production-код просто не викликатиме його).
        // Якщо викликаний — реальна реалізація запише $this->mappedBy на mock; нам це не заважає.
        $state = (object) ['noLimit' => false];

        $mock->method('noLimit')->willReturnCallback(function () use ($state, $mock) {
            $state->noLimit = true;
            return $mock;
        });
        $mock->method('cols')->willReturnCallback(static fn () => $mock);

        $defaultLimit = self::DEFAULT_SELECT_LIMIT;
        $mock->method('find')->willReturnCallback(function (array $filter = []) use ($state, $records, $filterableFields, $defaultMappedByField, $defaultLimit) {
            $result = $records;
            foreach ($filterableFields as $field) {
                if (!isset($filter[$field])) {
                    continue;
                }
                $needle = array_map('intval', (array) $filter[$field]);
                $result = array_values(array_filter(
                    $result,
                    static fn ($record) => isset($record->{$field}) && in_array((int) $record->{$field}, $needle, true)
                ));
            }

            $noLimit = $state->noLimit;
            $state->noLimit = false;

            if (!$noLimit && count($result) > $defaultLimit) {
                $result = array_slice($result, 0, $defaultLimit);
            }

            if ($defaultMappedByField !== null) {
                $mapped = [];
                foreach ($result as $record) {
                    if (isset($record->{$defaultMappedByField})) {
                        $mapped[$record->{$defaultMappedByField}] = $record;
                    }
                }
                return $mapped;
            }

            return $result;
        });

        return $mock;
    }
}
