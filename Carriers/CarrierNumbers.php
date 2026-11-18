<?php

namespace Okay\Modules\Sviat\OrdersExport\Carriers;

/** Номери відправлень одного перевізника. */
interface CarrierNumbers extends NumberSource
{
    /** Ключ перевізника з DeliveryCarriers; за ним агрегатор складає мапу адаптерів. */
    public function carrier(): string;
}
