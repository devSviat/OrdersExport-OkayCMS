<?php

/**
 * Заглушка фасаду сусіднього модуля Sviat/Ukrposhta.
 *
 * Модуль необов'язковий; у прогоні релізу в рушій кладуть лише Messaging. Файл
 * підключається лише за відсутності оригіналу, тож на стенді працює справжній клас.
 * Однакова в усіх модулях-споживачах: у спільному прогоні вантажиться та, що трапиться
 * першою, тож кожна мусить мати всі методи фасаду.
 */

namespace Okay\Modules\Sviat\Ukrposhta\Integration;

final class UpOrders
{
    public function shipments(array $orderIds): array
    {
        return [];
    }

    public function unfinished(): array
    {
        return [];
    }

    /** @return list<int> */
    public function receivedSince(string $since): array
    {
        return [];
    }

    public function addressOf(int $orderId): ?object
    {
        return null;
    }

    public static function withBarcodeSql(): string
    {
        return "SELECT order_id FROM __sviat__ukrposhta__shipments WHERE state = 'created' AND barcode IS NOT NULL AND barcode <> ''";
    }

    public static function shipment(object $row, ?object $delivery): ?object
    {
        return null;
    }

    public static function address(?object $delivery): ?object
    {
        return null;
    }
}
