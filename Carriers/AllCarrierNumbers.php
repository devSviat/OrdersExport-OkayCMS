<?php

namespace Okay\Modules\Sviat\OrdersExport\Carriers;

/**
 * Номери всіх перевізників. Коли номер замовлення є в обох, береться перевізник поточної доставки:
 * менеджер міг створити накладну НП, а потім перевести замовлення на Укрпошту. Доставка без
 * перевізника: Нова Пошта.
 */
final class AllCarrierNumbers implements NumberSource
{
    /** @var array<string, CarrierNumbers> за ключем перевізника */
    private readonly array $byCarrier;

    /** Адаптери — варіадиком, бо контейнер не розв'язує ServiceReference усередині масиву. */
    public function __construct(private readonly DeliveryCarriers $deliveryCarriers, CarrierNumbers ...$adapters)
    {
        $byCarrier = [];
        foreach ($adapters as $adapter) {
            $byCarrier[$adapter->carrier()] = $adapter;
        }
        $this->byCarrier = $byCarrier;
    }

    public function available(): bool
    {
        foreach ($this->byCarrier as $adapter) {
            if ($adapter->available()) {
                return true;
            }
        }

        return false;
    }

    public function numbers(array $orderIds): array
    {
        /** @var array<int, array<string, string>> $candidates */
        $candidates = [];
        foreach ($this->byCarrier as $carrier => $adapter) {
            foreach ($adapter->numbers($orderIds) as $orderId => $number) {
                $candidates[$orderId][$carrier] = $number;
            }
        }
        $contested = array_keys(array_filter($candidates, static fn(array $byCarrier): bool => count($byCarrier) > 1));
        $deliveryOf = $contested === [] ? [] : $this->deliveryCarriers->ofOrders($contested);

        $result = [];
        foreach ($candidates as $orderId => $byCarrier) {
            $result[$orderId] = self::pick($byCarrier, $deliveryOf[$orderId] ?? null);
        }

        return $result;
    }

    public function ordersWithNumberSql(): ?string
    {
        $parts = array_filter(array_map(
            static fn(CarrierNumbers $adapter): ?string => $adapter->ordersWithNumberSql(),
            array_values($this->byCarrier)
        ));

        return $parts === [] ? null : implode(' UNION ', $parts);
    }

    /**
     * Порядок адаптерів на вибір не впливає. Перемагає перевізник доставки; без збігу береться Нова Пошта;
     * без неї — перший за алфавітом ключа перевізника.
     *
     * @param non-empty-array<string, string> $byCarrier
     */
    private static function pick(array $byCarrier, ?string $delivery): string
    {
        if ($delivery !== null && isset($byCarrier[$delivery])) {
            return $byCarrier[$delivery];
        }
        if (isset($byCarrier[DeliveryCarriers::NOVA_POSHTA])) {
            return $byCarrier[DeliveryCarriers::NOVA_POSHTA];
        }
        ksort($byCarrier);

        return reset($byCarrier);
    }
}
