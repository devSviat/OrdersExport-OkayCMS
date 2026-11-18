<?php

namespace Modules\Sviat\OrdersExport\Carriers;

use Okay\Core\Modules\Modules;
use Okay\Modules\Sviat\Meest\Integration\MeestOrders;
use Okay\Modules\Sviat\OrdersExport\Carriers\MeestBarcodes;
use PHPUnit\Framework\TestCase;

if (!class_exists(MeestOrders::class)) {
    require_once __DIR__ . '/../MeestOrdersStub.php';
}

/** Штрихкоди посилок Meest в колонці «ТТН доставки» експорту. */
class MeestBarcodesTest extends TestCase
{
    private function source(bool $active, array $barcodes = []): MeestBarcodes
    {
        $modules = $this->createStub(Modules::class);
        $modules->method('isActiveModule')->willReturnCallback(static fn(string $vendor, string $name): bool => $name === 'Meest' && $active);
        $facade = new class ($barcodes) {
            public function __construct(private array $barcodes)
            {
            }

            public function parcels(array $orderIds): array
            {
                $result = [];
                foreach (array_intersect_key($this->barcodes, array_flip($orderIds)) as $orderId => $barcode) {
                    $result[$orderId] = (object)['orderId' => $orderId, 'barcode' => $barcode];
                }

                return $result;
            }
        };

        return new MeestBarcodes($modules, $active ? static fn(): object => $facade : static fn(): never => throw new \LogicException('facade built while the module is disabled'));
    }

    public function testNumbersFromFacade(): void
    {
        $source = $this->source(true, [1 => 'CC-0001', 3 => 'CC-0003']);

        self::assertSame('meest', $source->carrier());
        self::assertTrue($source->available());
        self::assertSame([1 => 'CC-0001', 3 => 'CC-0003'], $source->numbers([1, 2, 3]));
    }

    public function testSqlEqualsFacadeSubquery(): void
    {
        self::assertSame(MeestOrders::withBarcodeSql(), $this->source(true)->ordersWithNumberSql());
    }

    public function testMeestDisabledGivesNothing(): void
    {
        $source = $this->source(false);

        self::assertFalse($source->available());
        self::assertSame([], $source->numbers([1]));
        self::assertNull($source->ordersWithNumberSql());
    }
}
