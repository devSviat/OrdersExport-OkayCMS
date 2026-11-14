<?php

namespace Okay\Modules\Sviat\OrdersExport\Carriers;

/** Номери відправлень перевізника для колонки «ТТН доставки» й фільтра has_ttn. */
interface CarrierNumbers
{
    /** Чи ввімкнений хоч один модуль, що дає номери. */
    public function available(): bool;

    /**
     * @param list<int> $orderIds
     * @return array<int, string> номер за id замовлення
     */
    public function numbers(array $orderIds): array;

    /** Підзапит id замовлень, що мають номер; null, коли номерів немає ні в кого. */
    public function ordersWithNumberSql(): ?string;
}
