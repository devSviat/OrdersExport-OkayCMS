<?php

namespace Modules\Sviat\OrdersExport\Carriers;

use Okay\Core\EntityFactory;
use Okay\Entities\DeliveriesEntity;
use Okay\Entities\ModulesEntity;
use Okay\Entities\OrdersEntity;
use Okay\Modules\Sviat\OrdersExport\Carriers\AllCarrierNumbers;
use Okay\Modules\Sviat\OrdersExport\Carriers\CarrierNumbers;
use Okay\Modules\Sviat\OrdersExport\Carriers\DeliveryCarriers;
use PHPUnit\Framework\TestCase;

/**
 * Коли номери є від обох перевізників, виграє перевізник поточної доставки: менеджер міг створити
 * накладну НП і перевести замовлення на Укрпошту. Доставка без перевізника: Нова Пошта.
 */
class AllCarrierNumbersTest extends TestCase
{
    private const int NP_DELIVERY = 1;
    private const int UP_DELIVERY = 2;
    private const int PICKUP = 3;

    private static function carrier(bool $available, array $numbers, ?string $sql): CarrierNumbers
    {
        return new class ($available, $numbers, $sql) implements CarrierNumbers {
            public function __construct(private bool $available, private array $numbers, private ?string $sql)
            {
            }

            public function available(): bool
            {
                return $this->available;
            }

            public function numbers(array $orderIds): array
            {
                return array_intersect_key($this->numbers, array_flip($orderIds));
            }

            public function ordersWithNumberSql(): ?string
            {
                return $this->sql;
            }
        };
    }

    /** @param array<int, int> $deliveryOf id доставки за id замовлення */
    private function deliveryCarriers(array $deliveryOf, int &$lookups = 0): DeliveryCarriers
    {
        $orders = $this->createStub(OrdersEntity::class);
        $orders->method('find')->willReturnCallback(static function (array $filter) use ($deliveryOf, &$lookups): array {
            $lookups++;

            return array_map(
                static fn(int $id): object => (object)['id' => $id, 'delivery_id' => $deliveryOf[$id]],
                array_values(array_filter($filter['id'], static fn(int $id): bool => isset($deliveryOf[$id])))
            );
        });
        $deliveries = $this->createStub(DeliveriesEntity::class);
        $deliveries->method('find')->willReturn([
            (object)['id' => self::NP_DELIVERY, 'module_id' => 11],
            (object)['id' => self::UP_DELIVERY, 'module_id' => 12],
            (object)['id' => self::PICKUP, 'module_id' => 0],
        ]);
        $modules = $this->createStub(ModulesEntity::class);
        $modules->method('find')->willReturn([
            (object)['id' => 11, 'vendor' => 'Sviat', 'module_name' => 'NovaPoshta'],
            (object)['id' => 12, 'vendor' => 'Sviat', 'module_name' => 'Ukrposhta'],
        ]);
        $factory = $this->createStub(EntityFactory::class);
        $factory->method('get')->willReturnCallback(static fn(string $class) => match ($class) {
            OrdersEntity::class => $orders,
            DeliveriesEntity::class => $deliveries,
            ModulesEntity::class => $modules,
        });

        return new DeliveryCarriers($factory);
    }

    private function all(array $np, array $up, array $deliveryOf = [], int &$lookups = 0, bool $upAvailable = true, ?string $npSql = 'NP_SQL', ?string $upSql = 'UP_SQL'): AllCarrierNumbers
    {
        return new AllCarrierNumbers(
            self::carrier($np !== [] || $npSql !== null, $np, $npSql),
            self::carrier($upAvailable, $up, $upSql),
            $this->deliveryCarriers($deliveryOf, $lookups),
        );
    }

    public function testBothCarriersCurrentUkrposhtaDeliveryGivesBarcode(): void
    {
        $all = $this->all([1 => 'NP1', 2 => 'NP2'], [1 => 'UP1', 3 => 'UP3'], [1 => self::UP_DELIVERY]);

        self::assertEqualsCanonicalizing([1 => 'UP1', 2 => 'NP2', 3 => 'UP3'], $all->numbers([1, 2, 3]));
        self::assertSame('UP1', $all->numbers([1, 2, 3])[1]);
    }

    public function testBothCarriersCurrentNovaPoshtaDeliveryGivesWaybill(): void
    {
        $all = $this->all([1 => 'NP1'], [1 => 'UP1'], [1 => self::NP_DELIVERY]);

        self::assertSame([1 => 'NP1'], $all->numbers([1]));
    }

    public function testBothCarriersOtherDeliveryGivesWaybill(): void
    {
        $all = $this->all([1 => 'NP1'], [1 => 'UP1'], [1 => self::PICKUP]);

        self::assertSame([1 => 'NP1'], $all->numbers([1]));
    }

    public function testBothCarriersLookupFindsNothingGivesWaybill(): void
    {
        $all = $this->all([1 => 'NP1'], [1 => 'UP1'], []);

        self::assertSame([1 => 'NP1'], $all->numbers([1]));
    }

    public function testNoLookupWithoutOverlapAndOneLookupForAll(): void
    {
        $lookups = 0;
        $this->all([1 => 'NP1'], [2 => 'UP2'], [], $lookups)->numbers([1, 2]);
        self::assertSame(0, $lookups);

        $this->all([1 => 'NP1', 2 => 'NP2', 3 => 'NP3'], [1 => 'UP1', 2 => 'UP2', 3 => 'UP3'], [1 => self::UP_DELIVERY, 2 => self::NP_DELIVERY, 3 => self::UP_DELIVERY], $lookups)->numbers([1, 2, 3]);
        self::assertSame(1, $lookups);
    }

    public function testUnionOfSql(): void
    {
        self::assertSame('NP_SQL UNION UP_SQL', $this->all([1 => 'NP1'], [])->ordersWithNumberSql());
    }

    public function testUkrposhtaDisabledNoUnion(): void
    {
        self::assertSame('NP_SQL', $this->all([1 => 'NP1'], [], [], upSql: null)->ordersWithNumberSql());
    }

    public function testNoneGivesNull(): void
    {
        $all = $this->all([], [], [], upAvailable: false, npSql: null, upSql: null);

        self::assertFalse($all->available());
        self::assertNull($all->ordersWithNumberSql());
        self::assertSame([], $all->numbers([1]));
    }
}
