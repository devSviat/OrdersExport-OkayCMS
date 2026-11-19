<?php

namespace Modules\Sviat\OrdersExport\Carriers;

use Okay\Core\EntityFactory;
use Okay\Entities\DeliveriesEntity;
use Okay\Entities\ModulesEntity;
use Okay\Entities\OrdersEntity;
use Okay\Modules\Sviat\OrdersExport\Carriers\DeliveryCarriers;
use PHPUnit\Framework\TestCase;

class DeliveryCarriersTest extends TestCase
{
    /**
     * @param array<int, int> $deliveryOf id доставки за id замовлення
     * @param array<int, int> $moduleOf id модуля за id доставки
     * @param list<object> $modules
     */
    private function carriers(array $deliveryOf, array $moduleOf, array $modules): DeliveryCarriers
    {
        $orders = $this->createStub(OrdersEntity::class);
        $orders->method('find')->willReturn(array_map(
            static fn(int $id): object => (object)['id' => $id, 'delivery_id' => $deliveryOf[$id]],
            array_keys($deliveryOf)
        ));
        $deliveries = $this->createStub(DeliveriesEntity::class);
        $deliveries->method('find')->willReturn(array_map(
            static fn(int $id): object => (object)['id' => $id, 'module_id' => $moduleOf[$id]],
            array_keys($moduleOf)
        ));
        $moduleEntity = $this->createStub(ModulesEntity::class);
        $moduleEntity->method('find')->willReturn($modules);
        $factory = $this->createStub(EntityFactory::class);
        $factory->method('get')->willReturnCallback(static fn(string $class) => match ($class) {
            OrdersEntity::class => $orders,
            DeliveriesEntity::class => $deliveries,
            ModulesEntity::class => $moduleEntity,
        });

        return new DeliveryCarriers($factory);
    }

    public function testNovaPoshtaModulesAreExplicit(): void
    {
        $carriers = $this->carriers(
            [1 => 1, 2 => 2, 3 => 3, 4 => 4, 5 => 5, 6 => 6, 7 => 7, 8 => 8],
            [1 => 11, 2 => 12, 3 => 13, 4 => 14, 5 => 0, 6 => 15, 7 => 16, 8 => 17],
            [
                (object)['id' => 11, 'vendor' => 'Sviat', 'module_name' => 'NovaPoshta'],
                (object)['id' => 12, 'vendor' => 'OkayCMS', 'module_name' => 'NovaposhtaCost'],
                (object)['id' => 13, 'vendor' => 'Sviat', 'module_name' => 'Ukrposhta'],
                (object)['id' => 14, 'vendor' => 'Sviat', 'module_name' => 'Unknown'],
                (object)['id' => 15, 'vendor' => 'Sviat', 'module_name' => 'Meest'],
                (object)['id' => 16, 'vendor' => 'Sviat', 'module_name' => 'NovaPoshtaTracking'],
                (object)['id' => 17, 'vendor' => 'Sviat', 'module_name' => 'RozetkaDelivery'],
            ]
        );

        self::assertSame(
            [1 => 'novaposhta', 2 => 'novaposhta', 3 => 'ukrposhta', 6 => 'meest', 7 => 'novaposhta', 8 => 'rozetka'],
            $carriers->ofOrders([1, 2, 3, 4, 5, 6, 7, 8])
        );
    }

    public function testNoOrdersNoQueries(): void
    {
        self::assertSame([], $this->carriers([], [], [])->ofOrders([]));
    }
}
