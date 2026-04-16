<?php


namespace Okay\Modules\Sviat\OrdersExport\Extenders;

use Okay\Core\EntityFactory;
use Okay\Core\Modules\Extender\ExtensionInterface;
use Okay\Core\Request;
use Okay\Core\Settings;
use Okay\Entities\ProductsEntity;
use Okay\Entities\PurchasesEntity;

class BackendExtender implements ExtensionInterface
{
    private Settings $settings;
    private Request $request;
    private EntityFactory $entityFactory;

    public function __construct(Settings $settings, Request $request, EntityFactory $entityFactory)
    {
        $this->settings = $settings;
        $this->request = $request;
        $this->entityFactory = $entityFactory;
    }

    public function buildFilter($filter)
    {
        if (!(bool) $this->settings->get('sviat__orders_export__show_orders_brand_filter')) {
            return $filter;
        }

        $brandIds = $this->normalizeBrandIds($this->request->get('brand_ids'));
        $filter = $this->appendBrandOrdersFilter($filter, $brandIds);

        return $filter;
    }

    public function buildCountStatusesFilter($filter)
    {
        if (!(bool) $this->settings->get('sviat__orders_export__show_orders_brand_filter')) {
            return $filter;
        }

        $brandIds = $this->normalizeBrandIds($this->request->get('brand_ids'));
        $filter = $this->appendBrandOrdersFilter($filter, $brandIds);

        return $filter;
    }

    private function appendBrandOrdersFilter(array $filter, array $brandIds): array
    {
        if (empty($brandIds)) {
            return $filter;
        }

        /** @var ProductsEntity $productsEntity */
        $productsEntity = $this->entityFactory->get(ProductsEntity::class);
        /** @var PurchasesEntity $purchasesEntity */
        $purchasesEntity = $this->entityFactory->get(PurchasesEntity::class);

        $productIds = $productsEntity->noLimit()->cols(['id'])->find(['brand_id' => $brandIds]);
        if (empty($productIds)) {
            $filter['id'] = [-1];
            return $filter;
        }

        $normalizedProductIds = [];
        foreach ($productIds as $productId) {
            $productId = (int) $productId;
            if ($productId > 0) {
                $normalizedProductIds[$productId] = $productId;
            }
        }

        if (empty($normalizedProductIds)) {
            $filter['id'] = [-1];
            return $filter;
        }

        $purchaseOrderIds = $purchasesEntity->noLimit()->cols(['order_id'])->find([
            'product_id' => array_values($normalizedProductIds),
        ]);
        if (empty($purchaseOrderIds)) {
            $filter['id'] = [-1];
            return $filter;
        }

        $orderIds = [];
        foreach ($purchaseOrderIds as $orderId) {
            $orderId = (int) $orderId;
            if ($orderId > 0) {
                $orderIds[$orderId] = $orderId;
            }
        }

        $filter['id'] = !empty($orderIds) ? array_values($orderIds) : [-1];

        return $filter;
    }

    /**
     * @param mixed $rawBrandIds
     * @return array<int>
     */
    private function normalizeBrandIds($rawBrandIds): array
    {
        if (is_string($rawBrandIds)) {
            $rawBrandIds = explode(',', $rawBrandIds);
        } elseif (!is_array($rawBrandIds)) {
            return [];
        }

        $brandIds = [];
        foreach ($rawBrandIds as $rawBrandId) {
            $brandId = (int) $rawBrandId;
            if ($brandId > 0) {
                $brandIds[$brandId] = $brandId;
            }
        }

        return array_values($brandIds);
    }
}
