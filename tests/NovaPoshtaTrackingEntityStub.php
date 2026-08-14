<?php

/**
 * Заглушка сутності сусіднього модуля Sviat/NovaPoshtaTracking.
 *
 * Модуль необов'язковий, і в прогоні релізу в рушій кладуть лише цей модуль —
 * тоді справжнього класу немає, а createMock() не має що дублювати. Файл
 * підключається лише за відсутності оригіналу, тож у робочому оточенні
 * використовується справжня сутність.
 */

namespace Okay\Modules\Sviat\NovaPoshtaTracking\Entities;

use Okay\Core\Entity\Entity;

class NovaPoshtaTrackingEntity extends Entity
{
    protected static $fields = [
        'id',
        'order_id',
        'int_doc_number',
    ];

    protected static $defaultOrderFields = ['id DESC'];
    protected static $table = 'sviat__novaposhta_tracking';
    protected static $tableAlias = 'npt';
}
