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
    private const int MEEST_DELIVERY = 4;
    private const int ROZETKA_DELIVERY = 5;

    private static function carrier(string $key, bool $available, array $numbers, ?string $sql): CarrierNumbers
    {
        return new class ($key, $available, $numbers, $sql) implements CarrierNumbers {
            public function __construct(private string $key, private bool $available, private array $numbers, private ?string $sql)
            {
            }

            public function carrier(): string
            {
                return $this->key;
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
            (object)['id' => self::MEEST_DELIVERY, 'module_id' => 13],
            (object)['id' => self::ROZETKA_DELIVERY, 'module_id' => 14],
        ]);
        $modules = $this->createStub(ModulesEntity::class);
        $modules->method('find')->willReturn([
            (object)['id' => 11, 'vendor' => 'Sviat', 'module_name' => 'NovaPoshta'],
            (object)['id' => 12, 'vendor' => 'Sviat', 'module_name' => 'Ukrposhta'],
            (object)['id' => 13, 'vendor' => 'Sviat', 'module_name' => 'Meest'],
            (object)['id' => 14, 'vendor' => 'Sviat', 'module_name' => 'RozetkaDelivery'],
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
            $this->deliveryCarriers($deliveryOf, $lookups),
            self::carrier('novaposhta', $np !== [] || $npSql !== null, $np, $npSql),
            self::carrier('ukrposhta', $upAvailable, $up, $upSql),
        );
    }

    private function withMeest(array $deliveryOf, CarrierNumbers ...$carriers): AllCarrierNumbers
    {
        return new AllCarrierNumbers($this->deliveryCarriers($deliveryOf), ...$carriers);
    }

    /** Покупець обрав ROZETKA після того, як менеджер уже створив номер іншого перевізника. */
    public function testRozetkaWinsWhenDeliveryIsRozetka(): void
    {
        $all = $this->withMeest(
            [1 => self::ROZETKA_DELIVERY, 2 => self::ROZETKA_DELIVERY],
            self::carrier('novaposhta', true, [1 => 'NP1'], 'NP_SQL'),
            self::carrier('meest', true, [2 => 'MS-2'], 'MS_SQL'),
            self::carrier('rozetka', true, [1 => '101000000001', 2 => '101000000002'], 'RZ_SQL'),
        );

        self::assertSame([1 => '101000000001', 2 => '101000000002'], $all->numbers([1, 2]));
        self::assertSame('NP_SQL UNION MS_SQL UNION RZ_SQL', $all->ordersWithNumberSql());
    }

    /** Покинута ЕН ROZETKA після зміни доставки не заміняє номер поточного перевізника. */
    public function testAbandonedRozetkaLosesToCurrentDelivery(): void
    {
        $all = $this->withMeest(
            [1 => self::NP_DELIVERY, 2 => self::UP_DELIVERY, 3 => self::MEEST_DELIVERY],
            self::carrier('rozetka', true, [1 => 'RZ1', 2 => 'RZ2', 3 => 'RZ3', 4 => 'RZ4'], 'RZ_SQL'),
            self::carrier('novaposhta', true, [1 => 'NP1'], 'NP_SQL'),
            self::carrier('ukrposhta', true, [2 => 'UP2'], 'UP_SQL'),
            self::carrier('meest', true, [3 => 'MS-3'], 'MS_SQL'),
        );

        self::assertSame([1 => 'NP1', 2 => 'UP2', 3 => 'MS-3', 4 => 'RZ4'], $all->numbers([1, 2, 3, 4]));
    }

    public function testMeestWinsWhenDeliveryIsMeest(): void
    {
        $all = $this->withMeest(
            [1 => self::MEEST_DELIVERY],
            self::carrier('novaposhta', true, [1 => 'NP1'], 'NP_SQL'),
            self::carrier('meest', true, [1 => 'MS-1'], 'MS_SQL'),
        );

        self::assertSame([1 => 'MS-1'], $all->numbers([1]));
    }

    public function testMeestIgnoredWhenDeliveryIsNotMeest(): void
    {
        $np = $this->withMeest(
            [1 => self::NP_DELIVERY],
            self::carrier('novaposhta', true, [1 => 'NP1'], 'NP_SQL'),
            self::carrier('meest', true, [1 => 'MS-1'], 'MS_SQL'),
        );
        $up = $this->withMeest(
            [1 => self::UP_DELIVERY],
            self::carrier('meest', true, [1 => 'MS-1'], 'MS_SQL'),
            self::carrier('ukrposhta', true, [1 => 'UP1'], 'UP_SQL'),
        );

        self::assertSame([1 => 'NP1'], $np->numbers([1]));
        self::assertSame([1 => 'UP1'], $up->numbers([1]));
    }

    public function testNoCarrierDeliveryFallsBackToNp(): void
    {
        $all = $this->withMeest(
            [1 => self::PICKUP],
            self::carrier('meest', true, [1 => 'MS-1'], 'MS_SQL'),
            self::carrier('novaposhta', true, [1 => 'NP1'], 'NP_SQL'),
        );

        self::assertSame([1 => 'NP1'], $all->numbers([1]));
    }

    public function testNoCarrierDeliveryWithoutNpFirstKeyAlphabeticallyWins(): void
    {
        $all = $this->withMeest(
            [1 => self::PICKUP],
            self::carrier('ukrposhta', true, [1 => 'UP1'], 'UP_SQL'),
            self::carrier('meest', true, [1 => 'MS-1'], 'MS_SQL'),
        );

        self::assertSame([1 => 'MS-1'], $all->numbers([1]), 'meest < ukrposhta alphabetically; no carrier delivery and no NP');
    }

    public function testMeestAloneIsTaken(): void
    {
        $all = $this->withMeest(
            [],
            self::carrier('novaposhta', true, [], 'NP_SQL'),
            self::carrier('meest', true, [2 => 'MS-2'], 'MS_SQL'),
        );

        self::assertSame([2 => 'MS-2'], $all->numbers([1, 2]));
    }

    public function testMeestDisabledNoUnion(): void
    {
        $all = $this->withMeest(
            [],
            self::carrier('novaposhta', true, [], 'NP_SQL'),
            self::carrier('ukrposhta', true, [], 'UP_SQL'),
            self::carrier('meest', false, [], null),
        );

        self::assertSame('NP_SQL UNION UP_SQL', $all->ordersWithNumberSql());
    }

    public function testMeestEnabledJoinsUnion(): void
    {
        $all = $this->withMeest(
            [],
            self::carrier('novaposhta', true, [], 'NP_SQL'),
            self::carrier('meest', true, [], 'MS_SQL'),
        );

        self::assertSame('NP_SQL UNION MS_SQL', $all->ordersWithNumberSql());
    }

    public function testBothCarriersCurrentUkrposhtaDeliveryGivesBarcode(): void
    {
        $all = $this->all([1 => 'NP1', 2 => 'NP2'], [1 => 'UP1', 3 => 'UP3'], [1 => self::UP_DELIVERY]);

        self::assertEqualsCanonicalizing([1 => 'UP1', 2 => 'NP2', 3 => 'UP3'], $all->numbers([1, 2, 3]));
        self::assertSame('UP1', $all->numbers([1, 2, 3])[1]);
    }

    public function testAdapterOrderDoesNotMatter(): void
    {
        $deliveryOf = [1 => self::UP_DELIVERY, 2 => self::NP_DELIVERY, 3 => self::PICKUP];
        $np = [1 => 'NP1', 2 => 'NP2', 3 => 'NP3', 4 => 'NP4'];
        $up = [1 => 'UP1', 2 => 'UP2', 3 => 'UP3', 5 => 'UP5'];
        $direct = new AllCarrierNumbers(
            $this->deliveryCarriers($deliveryOf),
            self::carrier('novaposhta', true, $np, 'NP_SQL'),
            self::carrier('ukrposhta', true, $up, 'UP_SQL'),
        );
        $reversed = new AllCarrierNumbers(
            $this->deliveryCarriers($deliveryOf),
            self::carrier('ukrposhta', true, $up, 'UP_SQL'),
            self::carrier('novaposhta', true, $np, 'NP_SQL'),
        );

        self::assertEqualsCanonicalizing([1 => 'UP1', 2 => 'NP2', 3 => 'NP3', 4 => 'NP4', 5 => 'UP5'], $direct->numbers([1, 2, 3, 4, 5]));
        self::assertEqualsCanonicalizing($direct->numbers([1, 2, 3, 4, 5]), $reversed->numbers([1, 2, 3, 4, 5]));
        self::assertSame('UP1', $reversed->numbers([1, 2, 3, 4, 5])[1]);
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
