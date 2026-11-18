<?php

namespace Okay\Modules\Sviat\OrdersExport\Carriers;

use Okay\Core\EntityFactory;
use Okay\Entities\DeliveriesEntity;
use Okay\Entities\ModulesEntity;
use Okay\Entities\OrdersEntity;

/** Визначає перевізника поточної доставки замовлення за модулем доставки. */
final class DeliveryCarriers
{
    public const string NOVA_POSHTA = 'novaposhta';
    public const string UKRPOSHTA = 'ukrposhta';
    public const string MEEST = 'meest';

    /** Модулі доставки за «Vendor/Module»; старі модулі НП підтримуються, поки вони є на сайті. */
    private const array MODULES = [
        'Sviat/NovaPoshta' => self::NOVA_POSHTA,
        'Sviat/NovaPoshtaTracking' => self::NOVA_POSHTA,
        'OkayCMS/NovaposhtaCost' => self::NOVA_POSHTA,
        'Sviat/Ukrposhta' => self::UKRPOSHTA,
        'Sviat/Meest' => self::MEEST,
    ];

    public function __construct(private readonly EntityFactory $entityFactory)
    {
    }

    /**
     * Три пакетні запити на будь-яку кількість замовлень.
     *
     * @param list<int> $orderIds
     * @return array<int, string> ключ перевізника за id замовлення; доставки без модуля перевізника немає
     */
    public function ofOrders(array $orderIds): array
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
        $carrierOfModule = [];
        foreach ($this->entityFactory->get(ModulesEntity::class)->find(['id' => $moduleIds, 'limit' => count($moduleIds)]) as $module) {
            $carrier = self::MODULES[$module->vendor . '/' . $module->module_name] ?? null;
            if ($carrier !== null) {
                $carrierOfModule[(int)$module->id] = $carrier;
            }
        }

        $result = [];
        foreach ($deliveryOf as $orderId => $deliveryId) {
            $carrier = $carrierOfModule[$moduleOf[$deliveryId] ?? 0] ?? null;
            if ($carrier !== null) {
                $result[$orderId] = $carrier;
            }
        }

        return $result;
    }
}
