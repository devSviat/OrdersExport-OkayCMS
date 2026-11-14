<?php

namespace Okay\Modules\Sviat\OrdersExport\Carriers;

/**
 * Номери всіх перевізників. Коли номер замовлення є в обох, береться перевізник поточної доставки:
 * менеджер міг створити накладну НП, а потім перевести замовлення на Укрпошту. Доставка без
 * перевізника: Нова Пошта.
 */
final class AllCarrierNumbers implements CarrierNumbers
{
    public function __construct(
        private readonly CarrierNumbers $novaPoshta,
        private readonly CarrierNumbers $ukrposhta,
        private readonly DeliveryCarriers $deliveryCarriers,
    ) {
    }

    public function available(): bool
    {
        return $this->novaPoshta->available() || $this->ukrposhta->available();
    }

    public function numbers(array $orderIds): array
    {
        $novaPoshta = $this->novaPoshta->numbers($orderIds);
        $ukrposhta = $this->ukrposhta->numbers($orderIds);
        $both = array_keys(array_intersect_key($novaPoshta, $ukrposhta));
        if ($both !== []) {
            $ukrposhtaOrders = array_flip($this->deliveryCarriers->ukrposhtaOrders($both));
            foreach ($both as $orderId) {
                if (isset($ukrposhtaOrders[$orderId])) {
                    unset($novaPoshta[$orderId]);
                } else {
                    unset($ukrposhta[$orderId]);
                }
            }
        }

        return $novaPoshta + $ukrposhta;
    }

    public function ordersWithNumberSql(): ?string
    {
        $parts = array_filter([$this->novaPoshta->ordersWithNumberSql(), $this->ukrposhta->ordersWithNumberSql()]);

        return $parts === [] ? null : implode(' UNION ', $parts);
    }
}
