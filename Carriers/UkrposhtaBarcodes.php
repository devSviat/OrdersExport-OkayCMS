<?php

namespace Okay\Modules\Sviat\OrdersExport\Carriers;

use Closure;
use Okay\Core\Modules\Modules;
use Okay\Core\ServiceLocator;
use Okay\Modules\Sviat\Ukrposhta\Integration\UpOrders;

/** Штрихкоди створених відправлень модуля Sviat/Ukrposhta; без модуля або коли він вимкнений, їх немає. */
final class UkrposhtaBarcodes implements CarrierNumbers
{
    private readonly Closure $upOrders;

    /** @param ?Closure(): object $upOrders фасад UpOrders; береться лише коли модуль увімкнений */
    public function __construct(private readonly Modules $modules, ?Closure $upOrders = null)
    {
        $this->upOrders = $upOrders ?? static fn(): object => ServiceLocator::getInstance()->getService(UpOrders::class);
    }

    public function available(): bool
    {
        return class_exists(UpOrders::class) && $this->modules->isActiveModule('Sviat', 'Ukrposhta');
    }

    public function numbers(array $orderIds): array
    {
        $orderIds = array_values(array_unique(array_map(intval(...), $orderIds)));
        if ($orderIds === [] || !$this->available()) {
            return [];
        }
        $numbers = [];
        foreach (($this->upOrders)()->shipments($orderIds) as $orderId => $shipment) {
            $numbers[(int)$orderId] = (string)$shipment->barcode;
        }

        return $numbers;
    }

    public function ordersWithNumberSql(): ?string
    {
        return $this->available() ? UpOrders::withBarcodeSql() : null;
    }
}
