<?php

namespace Modules\Sviat\OrdersExport;

use Okay\Core\EntityFactory;
use Okay\Core\Modules\Modules;
use Okay\Modules\Sviat\NovaPoshta\Integration\NpOrders;
use Okay\Modules\Sviat\NovaPoshtaTracking\Entities\NovaPoshtaTrackingEntity;
use Okay\Modules\Sviat\OrdersExport\Helpers\NovaPoshtaWaybills;
use PHPUnit\Framework\TestCase;

// Сусідні модулі необов'язкові: без них класів немає, а перевірки доступності питають саме про клас.
if (!class_exists(NpOrders::class)) {
    require_once __DIR__ . '/NpOrdersStub.php';
}
if (!class_exists(NovaPoshtaTrackingEntity::class)) {
    require_once __DIR__ . '/NovaPoshtaTrackingEntityStub.php';
}

/** Номери накладних для експорту — з модуля Sviat/NovaPoshta і старого NovaPoshtaTracking. */
class NovaPoshtaWaybillsTest extends TestCase
{
    private function source(bool $new, bool $old, array $newNumbers = [], array $oldRows = []): NovaPoshtaWaybills
    {
        $modules = $this->createStub(Modules::class);
        $modules->method('isActiveModule')->willReturnCallback(static fn(string $vendor, string $name): bool => ($name === 'NovaPoshta' && $new) || ($name === 'NovaPoshtaTracking' && $old));

        $tracking = $this->createStub(NovaPoshtaTrackingEntity::class);
        $tracking->method('noLimit')->willReturnSelf();
        $tracking->method('find')->willReturnCallback(static fn(array $filter): array => array_values(array_filter($oldRows, static fn(object $r): bool => in_array((int)$r->order_id, $filter['order_id'], true))));
        $entityFactory = $this->createStub(EntityFactory::class);
        $entityFactory->method('get')->willReturn($tracking);

        $facade = new class ($newNumbers) {
            public function __construct(private array $numbers)
            {
            }

            public function shipments(array $orderIds): array
            {
                $result = [];
                foreach (array_intersect_key($this->numbers, array_flip($orderIds)) as $orderId => $number) {
                    $result[$orderId] = (object)['orderId' => $orderId, 'number' => $number];
                }

                return $result;
            }
        };

        return new NovaPoshtaWaybills($modules, $entityFactory, static fn(): object => $facade);
    }

    private static function oldRow(int $orderId, string $number): object
    {
        return (object)['order_id' => $orderId, 'int_doc_number' => $number];
    }

    public function testNewModuleOnly(): void
    {
        $source = $this->source(true, false, [1 => '20450000000001']);

        self::assertTrue($source->available());
        self::assertSame([1 => '20450000000001'], $source->numbers([1, 2]));
    }

    public function testOldModuleOnly(): void
    {
        $source = $this->source(false, true, [], [self::oldRow(2, '20450000000002'), self::oldRow(3, '')]);

        self::assertSame([2 => '20450000000002'], $source->numbers([2, 3]));
    }

    public function testBothNewOverridesOld(): void
    {
        $source = $this->source(true, true, [1 => '20450000000111'], [self::oldRow(1, '20450000000001'), self::oldRow(2, '20450000000002')]);

        self::assertSame([1 => '20450000000111', 2 => '20450000000002'], $source->numbers([1, 2]));
    }

    public function testNeither(): void
    {
        $source = $this->source(false, false, [1 => '20450000000001'], [self::oldRow(2, '20450000000002')]);

        self::assertFalse($source->available());
        self::assertSame([], $source->numbers([1, 2]));
        self::assertNull($source->ordersWithWaybillSql());
    }

    public function testSubqueryForNewModule(): void
    {
        $sql = $this->source(true, false)->ordersWithWaybillSql();

        self::assertSame("SELECT order_id FROM " . NpOrders::waybillTable() . " WHERE number <> ''", $sql);
    }

    public function testSubqueryForBothIsAUnion(): void
    {
        $sql = $this->source(true, true)->ordersWithWaybillSql();

        self::assertStringContainsString(NpOrders::waybillTable(), $sql);
        self::assertStringContainsString(' UNION ', $sql);
        self::assertStringContainsString(NovaPoshtaTrackingEntity::getTable() . " WHERE int_doc_number IS NOT NULL AND int_doc_number <> ''", $sql);
    }
}
