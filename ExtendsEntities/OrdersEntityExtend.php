<?php


namespace Okay\Modules\Sviat\OrdersExport\ExtendsEntities;

use Okay\Core\Modules\AbstractModuleEntityFilter;
use Okay\Entities\OrdersEntity as OriginalOrdersEntity;

/**
 * Розширення OrdersEntity для фільтрації замовлень з ТТН
 */
class OrdersEntityExtend extends AbstractModuleEntityFilter
{
    /**
     * Фільтрує замовлення, які мають ТТН
     * 
     * @param mixed $value Значення фільтра (true, 1 або '1')
     * @param array $filter Масив фільтрів
     */
    public function filter__has_ttn($value, $filter)
    {
        if ($value !== true && $value !== 1 && $value !== '1') {
            return;
        }

        $tableAlias = OriginalOrdersEntity::getTableAlias();
        $trackingTable = 'sviat__novaposhta_tracking';

        $this->select->join('INNER', "{$trackingTable} AS npt_has_ttn", "{$tableAlias}.id = npt_has_ttn.order_id");
        $this->select->where('npt_has_ttn.int_doc_number IS NOT NULL');
        $this->select->where("npt_has_ttn.int_doc_number != ''");
        $this->select->groupBy([$tableAlias . '.id']);
    }
}
