<?php

/**
 * Заглушка фасаду сусіднього модуля Sviat/RozetkaDelivery.
 *
 * Модуль необов'язковий; у прогоні релізу в рушій кладуть лише модуль-споживач. Файл
 * підключається лише за відсутності оригіналу, тож на стенді працює справжній клас.
 * Однакова в усіх модулях-споживачах: у спільному прогоні вантажиться та, що трапиться
 * першою, тож кожна мусить мати всі методи фасаду.
 */

namespace Okay\Modules\Sviat\RozetkaDelivery\Integration;

final class RozetkaOrders
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

    public function receivedCodSince(string $since): array
    {
        return [];
    }

    public static function withTrackSql(): string
    {
        return "SELECT order_id FROM __sviat__rozetka__parcels WHERE state = 'created' AND track_id IS NOT NULL AND track_id <> ''";
    }

    public static function parcel(object $row): ?object
    {
        return null;
    }

    public static function address(?object $snapshot): ?object
    {
        return null;
    }
}
