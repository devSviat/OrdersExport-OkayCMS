<?php

namespace Okay\Modules\Sviat\OrdersExport\Carriers;

use Closure;
use Okay\Core\Modules\Modules;
use Okay\Core\ServiceLocator;
use Okay\Modules\Sviat\RozetkaDelivery\Integration\RozetkaOrders;

/** Номери створених ЕН модуля Sviat/RozetkaDelivery; без модуля або коли він вимкнений, їх немає. */
final class RozetkaTracks implements CarrierNumbers
{
    private readonly Closure $rozetkaOrders;

    /** @param ?Closure(): object $rozetkaOrders фасад RozetkaOrders; береться лише коли модуль увімкнений */
    public function __construct(private readonly Modules $modules, ?Closure $rozetkaOrders = null)
    {
        $this->rozetkaOrders = $rozetkaOrders ?? static fn(): object => ServiceLocator::getInstance()->getService(RozetkaOrders::class);
    }

    public function carrier(): string
    {
        return DeliveryCarriers::ROZETKA;
    }

    public function available(): bool
    {
        return class_exists(RozetkaOrders::class) && $this->modules->isActiveModule('Sviat', 'RozetkaDelivery');
    }

    public function numbers(array $orderIds): array
    {
        $orderIds = array_values(array_unique(array_map(intval(...), $orderIds)));
        if ($orderIds === [] || !$this->available()) {
            return [];
        }
        $numbers = [];
        foreach (($this->rozetkaOrders)()->parcels($orderIds) as $orderId => $parcel) {
            $numbers[(int)$orderId] = (string)$parcel->trackId;
        }

        return $numbers;
    }

    public function ordersWithNumberSql(): ?string
    {
        return $this->available() ? RozetkaOrders::withTrackSql() : null;
    }
}
