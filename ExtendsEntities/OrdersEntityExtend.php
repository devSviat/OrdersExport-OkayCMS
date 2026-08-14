<?php


namespace Okay\Modules\Sviat\OrdersExport\ExtendsEntities;

use Okay\Core\Modules\AbstractModuleEntityFilter;
use Okay\Core\Modules\Modules;
use Okay\Core\ServiceLocator;
use Okay\Entities\OrdersEntity as OriginalOrdersEntity;
use Okay\Modules\Sviat\NovaPoshtaTracking\Entities\NovaPoshtaTrackingEntity;

/** Додаткові фільтри для експорту замовлень. */
class OrdersEntityExtend extends AbstractModuleEntityFilter
{
    /**
     * Окремим методом, щоб обидві гілки has_ttn були перевіримі: стан рушія в
     * тесті не підмінити.
     *
     * Дві умови, бо кожна закриває свій випадок: без класу нема в кого спитати
     * назву таблиці, без установленого модуля тієї таблиці нема в базі. Решта
     * модуля питає саме isActiveModule(), тож і тут так само.
     *
     * ServiceLocator, а не DI: фільтри сутностей створюються через голий new.
     */
    protected function hasTrackingModule(): bool
    {
        return class_exists(NovaPoshtaTrackingEntity::class)
            && ServiceLocator::getInstance()
                ->getService(Modules::class)
                ->isActiveModule('Sviat', 'NovaPoshtaTracking');
    }

    /** Фільтр замовлень, у яких є ТТН. */
    public function filter__has_ttn($value, $filter)
    {
        if ($value !== true && $value !== 1 && $value !== '1') {
            return;
        }

        $tableAlias = OriginalOrdersEntity::getTableAlias();

        // Модуль трекінгу необов'язковий. Без нього ТТН немає ні в кого, тож
        // порожній результат — правильна відповідь: пропустити фільтр означало б
        // віддати в експорт усі замовлення підряд.
        if (!$this->hasTrackingModule()) {
            $this->select->where('1 = 0');
            return;
        }

        $trackingTable = NovaPoshtaTrackingEntity::getTable();

        $this->select->join('INNER', "{$trackingTable} AS npt_has_ttn", "{$tableAlias}.id = npt_has_ttn.order_id");
        $this->select->where('npt_has_ttn.int_doc_number IS NOT NULL');
        $this->select->where("npt_has_ttn.int_doc_number != ''");
        $this->select->groupBy([$tableAlias . '.id']);
    }

    /** Фільтр замовлень за брендами товарів. */
    public function filter__brand_ids($brandIds, $filter)
    {
        if (is_string($brandIds)) {
            $brandIds = explode(',', $brandIds);
        } elseif (!is_array($brandIds) || empty($brandIds)) {
            return;
        }

        $normalizedBrandIds = [];
        foreach ($brandIds as $brandId) {
            $brandId = (int) $brandId;
            if ($brandId > 0) {
                $normalizedBrandIds[$brandId] = $brandId;
            }
        }

        if (empty($normalizedBrandIds)) {
            return;
        }

        $tableAlias = OriginalOrdersEntity::getTableAlias();

        $this->select->where("{$tableAlias}.id IN (
            SELECT DISTINCT p_export_brand_filter.order_id
            FROM __purchases AS p_export_brand_filter
            INNER JOIN __products AS pr_export_brand_filter ON p_export_brand_filter.product_id = pr_export_brand_filter.id
            WHERE pr_export_brand_filter.brand_id IN (:export_brand_ids)
        )");
        $this->select->bindValue('export_brand_ids', array_values($normalizedBrandIds));
    }
}
