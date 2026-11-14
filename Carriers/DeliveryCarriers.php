<?php

namespace Okay\Modules\Sviat\OrdersExport\Carriers;

use Okay\Core\EntityFactory;
use Okay\Entities\DeliveriesEntity;
use Okay\Entities\ModulesEntity;
use Okay\Entities\OrdersEntity;

/** Визначає замовлення, поточна доставка яких прив'язана до модуля Sviat/Ukrposhta. */
final class DeliveryCarriers
{
    public function __construct(private readonly EntityFactory $entityFactory)
    {
    }

    /**
     * Три пакетні запити на будь-яку кількість замовлень.
     *
     * @param list<int> $orderIds
     * @return list<int> id замовлень із доставкою Укрпошти
     */
    public function ukrposhtaOrders(array $orderIds): array
    {
        if ($orderIds === []) {
            return [];
        }
        $deliveryOf = [];
        foreach ($this->entityFactory->get(OrdersEntity::class)->find(['id' => $orderIds, 'limit' => count($orderIds)]) as $order) {
            $deliveryOf[(int)$order->id] = (int)$order->delivery_id;
        }
        $deliveryIds = array_values(array_unique(array_filter($deliveryOf)));
        if ($deliveryIds === []) {
            return [];
        }
        $moduleOf = [];
        foreach ($this->entityFactory->get(DeliveriesEntity::class)->find(['id' => $deliveryIds, 'limit' => count($deliveryIds)]) as $delivery) {
            $moduleOf[(int)$delivery->id] = (int)($delivery->module_id ?? 0);
        }
        $moduleIds = array_values(array_unique(array_filter($moduleOf)));
        if ($moduleIds === []) {
            return [];
        }
        $ukrposhtaModules = [];
        foreach ($this->entityFactory->get(ModulesEntity::class)->find(['id' => $moduleIds, 'limit' => count($moduleIds)]) as $module) {
            if ($module->vendor === 'Sviat' && $module->module_name === 'Ukrposhta') {
                $ukrposhtaModules[(int)$module->id] = true;
            }
        }

        $result = [];
        foreach ($deliveryOf as $orderId => $deliveryId) {
            if (isset($ukrposhtaModules[$moduleOf[$deliveryId] ?? 0])) {
                $result[] = $orderId;
            }
        }

        return $result;
    }
}
