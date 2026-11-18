<?php

/**
 * Заглушка фасаду сусіднього модуля Sviat/Meest.
 *
 * Модуль необов'язковий; у прогоні релізу в рушій кладуть лише OrdersExport. Файл
 * підключається лише за відсутності оригіналу, тож на стенді працює справжній клас.
 * Однакова в усіх модулях-споживачах: у спільному прогоні вантажиться та, що трапиться
 * першою, тож кожна мусить мати всі методи фасаду.
 */

namespace Okay\Modules\Sviat\Meest\Integration;

final class MeestOrders
{
    public function parcels(array $orderIds): array
    {
        return [];
    }

    public function unfinished(): array
    {
        return [];
    }

    public function addressOf(int $orderId): ?object
    {
        return null;
    }

    public static function withBarcodeSql(): string
    {
        return "SELECT order_id FROM __sviat__meest__parcels WHERE state = 'created' AND barcode IS NOT NULL AND barcode <> ''";
    }

    public static function parcel(object $row): ?object
    {
        return null;
    }

    public static function address(?object $delivery): ?object
    {
        return null;
    }
}
