<?php

namespace Modules\Sviat\OrdersExport\Templates;

use PHPUnit\Framework\TestCase;

/** Модалка брендів — окрема GET-форма: усі фільтри сторінки мусять пройти через неї приховано, інакше бренд їх скидає. */
class BrandFilterFormTest extends TestCase
{
    private static function render(array $get): string
    {
        $root = dirname(__DIR__, 5);
        // Сток 4.5.3 має Smarty 4 (клас \Smarty), форк — Smarty 5 (\Smarty\Smarty); потрібні методи однакові.
        $class = class_exists('Smarty\\Smarty') ? 'Smarty\\Smarty' : 'Smarty';
        $smarty = new $class();
        $smarty->setTemplateDir([$root . '/Okay/Modules/Sviat/OrdersExport/Backend/design/html']);
        $smarty->setCompileDir(sys_get_temp_dir() . '/orders-export-brand-filter-' . getmypid());
        $smarty->muteUndefinedOrNullWarnings();
        $smarty->registerPlugin('function', 'get_brands', static function (array $params, $template): string {
            $template->assign($params['var'], []);

            return '';
        });
        $smarty->assign('settings', new class {
            public function get(string $name): bool
            {
                return true;
            }
        });
        $smarty->assign('btr', new class {
            public function __get(string $key): string
            {
                return $key;
            }
        });
        $saved = $_GET;
        $_GET = $get;
        try {
            return $smarty->fetch('orders_brand_filter.tpl');
        } finally {
            $_GET = $saved;
        }
    }

    public function testDeliveryStatusSurvivesTheBrandForm(): void
    {
        $html = self::render(['controller' => 'OrdersAdmin', 'delivery_status' => 'np:arrived', 'status' => '2']);

        self::assertStringContainsString('<input type="hidden" name="delivery_status" value="np:arrived">', $html);
        self::assertStringContainsString('<input type="hidden" name="status" value="2">', $html);
    }

    public function testNoDeliveryStatusNoInput(): void
    {
        self::assertStringNotContainsString('name="delivery_status"', self::render(['controller' => 'OrdersAdmin']));
    }

    public function testValueIsEscaped(): void
    {
        $html = self::render(['delivery_status' => '"><script>']);

        self::assertStringNotContainsString('"><script>', $html);
        self::assertStringContainsString('value="&quot;&gt;&lt;script&gt;"', $html);
    }

    /** Фільтр статусів старого модуля NovaPoshtaTracking, який досі підтримується поруч із delivery_status. */
    public function testNovaPoshtaTrackingStatusSurvivesTheBrandForm(): void
    {
        $html = self::render(['np_status' => '7']);

        self::assertStringContainsString('<input type="hidden" name="np_status" value="7">', $html);
        self::assertStringNotContainsString('name="delivery_status"', $html);
    }
}
