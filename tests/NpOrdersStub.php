<?php

/**
 * Заглушка фасаду сусіднього модуля Sviat/NovaPoshta.
 *
 * Модуль необов'язковий; у прогоні релізу в рушій кладуть лише OrdersExport. Файл
 * підключається лише за відсутності оригіналу, тож на стенді працює справжній клас.
 * Однакова в Checkbox, Messaging і OrdersExport: у спільному прогоні вантажиться та,
 * що трапиться першою, тож кожна мусить мати всі методи фасаду.
 */

namespace Okay\Modules\Sviat\NovaPoshta\Integration;

final class NpOrders
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

    public static function waybillTable(): string
    {
        return '__sviat__novaposhta_waybill';
    }
}
