<?php


namespace Okay\Modules\Sviat\OrdersExport\ExtendsEntities;

use Okay\Core\Modules\AbstractModuleEntityFilter;
use Okay\Core\ServiceLocator;
use Okay\Entities\OrdersEntity as OriginalOrdersEntity;
use Okay\Modules\Sviat\OrdersExport\Helpers\NovaPoshtaWaybills;

/** Додаткові фільтри для експорту замовлень. */
class OrdersEntityExtend extends AbstractModuleEntityFilter
{
    /**
     * Окремим методом, щоб обидві гілки has_ttn були перевіримі: стан рушія в
     * тесті не підмінити.
     *
     * ServiceLocator, а не DI: фільтри сутностей створюються через голий new.
     */
    protected function ttnOrdersSql(): ?string
    {
        return ServiceLocator::getInstance()->getService(NovaPoshtaWaybills::class)->ordersWithWaybillSql();
    }

    /**
     * Фільтр замовлень, у яких є ТТН — у модулі Sviat/NovaPoshta чи старому NovaPoshtaTracking.
     * Підзапит, а не JOIN: з двома модулями JOIN повторював би замовлення з накладною в обох.
     */
    public function filter__has_ttn($value, $filter)
    {
        if ($value !== true && $value !== 1 && $value !== '1') {
            return;
        }

        $tableAlias = OriginalOrdersEntity::getTableAlias();
        $sql = $this->ttnOrdersSql();

        // Модулі Нової Пошти необов'язкові. Без них ТТН немає ні в кого, тож
        // порожній результат — правильна відповідь: пропустити фільтр означало б
        // віддати в експорт усі замовлення підряд.
        if ($sql === null) {
            $this->select->where('1 = 0');
            return;
        }

        $this->select->where("{$tableAlias}.id IN ({$sql})");
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
