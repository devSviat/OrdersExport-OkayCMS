<?php

namespace Modules\Sviat\OrdersExport\Carriers;

use Okay\Core\Modules\Modules;
use Okay\Modules\Sviat\OrdersExport\Carriers\RozetkaTracks;
use Okay\Modules\Sviat\RozetkaDelivery\Integration\RozetkaOrders;
use PHPUnit\Framework\TestCase;

if (!class_exists(RozetkaOrders::class)) {
    require_once __DIR__ . '/../RozetkaOrdersStub.php';
}

/** Номери ЕН ROZETKA Delivery в колонці «ТТН доставки» експорту. */
class RozetkaTracksTest extends TestCase
{
    private function source(bool $active, array $tracks = []): RozetkaTracks
    {
        $modules = $this->createStub(Modules::class);
        $modules->method('isActiveModule')->willReturnCallback(static fn(string $vendor, string $name): bool => $vendor === 'Sviat' && $name === 'RozetkaDelivery' && $active);
        $facade = new class ($tracks) {
            public function __construct(private array $tracks)
            {
            }

            public function parcels(array $orderIds): array
            {
                $result = [];
                foreach (array_intersect_key($this->tracks, array_flip($orderIds)) as $orderId => $trackId) {
                    $result[$orderId] = (object)['orderId' => $orderId, 'trackId' => $trackId];
                }

                return $result;
            }
        };

        return new RozetkaTracks($modules, $active ? static fn(): object => $facade : static fn(): never => throw new \LogicException('facade built while the module is disabled'));
    }

    public function testNumbersFromFacade(): void
    {
        $source = $this->source(true, [1 => '101000000001', 3 => '101000000003']);

        self::assertSame('rozetka', $source->carrier());
        self::assertTrue($source->available());
        self::assertSame([1 => '101000000001', 3 => '101000000003'], $source->numbers([1, 2, 3]));
        self::assertSame([], $source->numbers([]));
    }

    public function testSqlEqualsFacadeSubquery(): void
    {
        self::assertSame(RozetkaOrders::withTrackSql(), $this->source(true)->ordersWithNumberSql());
    }

    public function testRozetkaDisabledGivesNothing(): void
    {
        $source = $this->source(false);

        self::assertFalse($source->available());
        self::assertSame([], $source->numbers([1]));
        self::assertNull($source->ordersWithNumberSql());
    }
}
