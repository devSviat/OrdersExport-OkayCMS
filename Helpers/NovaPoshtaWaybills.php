<?php

namespace Okay\Modules\Sviat\OrdersExport\Helpers;

use Closure;
use Okay\Core\EntityFactory;
use Okay\Core\Modules\Modules;
use Okay\Core\ServiceLocator;
use Okay\Modules\Sviat\NovaPoshta\Integration\NpOrders;
use Okay\Modules\Sviat\NovaPoshtaTracking\Entities\NovaPoshtaTrackingEntity;

/**
 * Номери накладних з модуля Sviat/NovaPoshta і старого NovaPoshtaTracking — того, що ввімкнений;
 * якщо накладна замовлення є в обох, береться нова.
 */
final class NovaPoshtaWaybills
{
    private readonly Closure $npOrders;

    /** @param ?Closure(): object $npOrders фасад NpOrders; береться лише коли модуль увімкнений */
    public function __construct(
        private readonly Modules $modules,
        private readonly EntityFactory $entityFactory,
        ?Closure $npOrders = null,
    ) {
        $this->npOrders = $npOrders ?? static fn(): object => ServiceLocator::getInstance()->getService(NpOrders::class);
    }

    public function available(): bool
    {
        return $this->newActive() || $this->oldActive();
    }

    /**
     * @param list<int> $orderIds
     * @return array<int, string> номер накладної за id замовлення
     */
    public function numbers(array $orderIds): array
    {
        $orderIds = array_values(array_unique(array_map(intval(...), $orderIds)));
        if ($orderIds === []) {
            return [];
        }
        $numbers = [];
        if ($this->oldActive()) {
            // noLimit(): дефолтний ліміт + defaultOrderFields=id DESC викидає ТТН старих замовлень при великих сторінках.
            foreach ($this->entityFactory->get(NovaPoshtaTrackingEntity::class)->noLimit()->find(['order_id' => $orderIds]) as $tracking) {
                if (!empty($tracking->int_doc_number)) {
                    $numbers[(int)$tracking->order_id] = (string)$tracking->int_doc_number;
                }
            }
        }
        if ($this->newActive()) {
            foreach (($this->npOrders)()->shipments($orderIds) as $orderId => $shipment) {
                $numbers[(int)$orderId] = (string)$shipment->number;
            }
        }

        return $numbers;
    }

    /** Підзапит id замовлень, що мають накладну; null — жоден модуль не ввімкнений. */
    public function ordersWithWaybillSql(): ?string
    {
        $parts = [];
        if ($this->newActive()) {
            $parts[] = 'SELECT order_id FROM ' . NpOrders::waybillTable() . " WHERE number <> ''";
        }
        if ($this->oldActive()) {
            $parts[] = 'SELECT order_id FROM ' . NovaPoshtaTrackingEntity::getTable() . " WHERE int_doc_number IS NOT NULL AND int_doc_number <> ''";
        }

        return $parts === [] ? null : implode(' UNION ', $parts);
    }

    private function newActive(): bool
    {
        return class_exists(NpOrders::class) && $this->modules->isActiveModule('Sviat', 'NovaPoshta');
    }

    private function oldActive(): bool
    {
        return class_exists(NovaPoshtaTrackingEntity::class) && $this->modules->isActiveModule('Sviat', 'NovaPoshtaTracking');
    }
}
