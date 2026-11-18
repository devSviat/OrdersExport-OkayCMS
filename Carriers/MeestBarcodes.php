<?php

namespace Okay\Modules\Sviat\OrdersExport\Carriers;

use Closure;
use Okay\Core\Modules\Modules;
use Okay\Core\ServiceLocator;
use Okay\Modules\Sviat\Meest\Integration\MeestOrders;

/** Штрихкоди створених посилок модуля Sviat/Meest; без модуля або коли він вимкнений, їх немає. */
final class MeestBarcodes implements CarrierNumbers
{
    private readonly Closure $meestOrders;

    /** @param ?Closure(): object $meestOrders фасад MeestOrders; береться лише коли модуль увімкнений */
    public function __construct(private readonly Modules $modules, ?Closure $meestOrders = null)
    {
        $this->meestOrders = $meestOrders ?? static fn(): object => ServiceLocator::getInstance()->getService(MeestOrders::class);
    }

    public function carrier(): string
    {
        return DeliveryCarriers::MEEST;
    }

    public function available(): bool
    {
        return class_exists(MeestOrders::class) && $this->modules->isActiveModule('Sviat', 'Meest');
    }

    public function numbers(array $orderIds): array
    {
        $orderIds = array_values(array_unique(array_map(intval(...), $orderIds)));
        if ($orderIds === [] || !$this->available()) {
            return [];
        }
        $numbers = [];
        foreach (($this->meestOrders)()->parcels($orderIds) as $orderId => $parcel) {
            $numbers[(int)$orderId] = (string)$parcel->barcode;
        }

        return $numbers;
    }

    public function ordersWithNumberSql(): ?string
    {
        return $this->available() ? MeestOrders::withBarcodeSql() : null;
    }
}
