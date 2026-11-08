<?php

/**
 * Заглушка фасаду сусіднього модуля Sviat/NovaPoshta.
 *
 * Модуль необов'язковий; у прогоні релізу в рушій кладуть лише OrdersExport. Файл
 * підключається лише за відсутності оригіналу, тож на стенді працює справжній клас.
 */

namespace Okay\Modules\Sviat\NovaPoshta\Integration;

final class NpOrders
{
    public function shipments(array $orderIds): array
    {
        return [];
    }

    public static function waybillTable(): string
    {
        return '__sviat__novaposhta_waybill';
    }
}
