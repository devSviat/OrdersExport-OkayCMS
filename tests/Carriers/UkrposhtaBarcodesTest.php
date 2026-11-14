<?php

namespace Modules\Sviat\OrdersExport\Carriers;

use Okay\Core\Modules\Modules;
use Okay\Modules\Sviat\OrdersExport\Carriers\UkrposhtaBarcodes;
use Okay\Modules\Sviat\Ukrposhta\Integration\UpOrders;
use PHPUnit\Framework\TestCase;

if (!class_exists(UpOrders::class)) {
    require_once __DIR__ . '/../UpOrdersStub.php';
}

/** Штрихкоди Укрпошти в колонці «ТТН доставки» експорту. */
class UkrposhtaBarcodesTest extends TestCase
{
    private function source(bool $active, array $barcodes = []): UkrposhtaBarcodes
    {
        $modules = $this->createStub(Modules::class);
        $modules->method('isActiveModule')->willReturnCallback(static fn(string $vendor, string $name): bool => $name === 'Ukrposhta' && $active);
        $facade = new class ($barcodes) {
            public function __construct(private array $barcodes)
            {
            }

            public function shipments(array $orderIds): array
            {
                $result = [];
                foreach (array_intersect_key($this->barcodes, array_flip($orderIds)) as $orderId => $barcode) {
                    $result[$orderId] = (object)['orderId' => $orderId, 'barcode' => $barcode];
                }

                return $result;
            }
        };

        return new UkrposhtaBarcodes($modules, $active ? static fn(): object => $facade : static fn(): never => throw new \LogicException('facade built while the module is disabled'));
    }

    public function testNumbersFromFacade(): void
    {
        $source = $this->source(true, [1 => '0500000001', 3 => '0500000003']);

        self::assertTrue($source->available());
        self::assertSame([1 => '0500000001', 3 => '0500000003'], $source->numbers([1, 2, 3]));
    }

    public function testDisabledModuleNeverBuildsFacade(): void
    {
        $source = $this->source(false);

        self::assertFalse($source->available());
        self::assertSame([], $source->numbers([1]));
        self::assertNull($source->ordersWithNumberSql());
    }

    public function testUkrposhtaSqlOnlyCreatedWithBarcode(): void
    {
        self::assertSame(UpOrders::withBarcodeSql(), $this->source(true)->ordersWithNumberSql());
    }
}
