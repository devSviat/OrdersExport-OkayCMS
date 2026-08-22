<?php

namespace Modules\Sviat\OrdersExport;

use Okay\Core\EntityFactory;
use Okay\Core\Modules\Modules;
use Okay\Core\Settings;
use Okay\Modules\Sviat\OrdersExport\Backend\Helpers\BackendOrdersExportHelper;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/RequestStub.php';

/**
 * Колонки, які додають сусідні модулі через extender на getColumnsNames.
 *
 * Свій case у switch мають лише колонки, відомі самому експорту; решта беруться
 * з поля замовлення за іменем ключа. Без цього extender додає заголовок, під
 * яким у кожному рядку порожньо.
 */
#[AllowMockObjectsWithoutExpectations]
class BackendOrdersExportHelperCustomColumnTest extends TestCase
{
    private const COLUMNS = [
        'order_id' => 'Номер замовлення',
        'product_name' => 'Назва товару',
        'date' => 'Дата замовлення',
        'device_model' => 'Модель / SN',
    ];

    private string $exportDir;

    protected function setUp(): void
    {
        $this->exportDir = sys_get_temp_dir() . '/orders_export_test_' . uniqid() . '/';
        mkdir($this->exportDir);
    }

    protected function tearDown(): void
    {
        array_map('unlink', glob($this->exportDir . '*') ?: []);
        rmdir($this->exportDir);
    }

    public function testWritesOrderFieldForColumnWithoutOwnCase(): void
    {
        $order = (object) [
            'id' => 17,
            'status_id' => 0,
            'date' => '2026-08-18',
            'device_model' => 'WW70J5210HW/UA',
        ];
        $purchases = [17 => [(object) ['product_name' => 'Ремінь приводний', 'sku' => 'BLT-1']]];

        $csv = $this->runExport([$order], $purchases);

        $this->assertStringContainsString('WW70J5210HW/UA', $csv);
    }

    public function testWritesOrderFieldForOrderWithoutPurchases(): void
    {
        $order = (object) [
            'id' => 18,
            'status_id' => 0,
            'date' => '2026-08-18',
            'device_model' => 'LG-F2V5GG9W',
        ];

        $csv = $this->runExport([$order], []);

        $this->assertStringContainsString('LG-F2V5GG9W', $csv);
    }

    public function testLeavesColumnEmptyWhenOrderHasNoSuchField(): void
    {
        $order = (object) ['id' => 19, 'status_id' => 0, 'date' => '2026-08-18'];

        $csv = $this->runExport([$order], []);

        $this->assertSame('19;;2026-08-18;', trim($csv));
    }

    /**
     * @param list<\stdClass> $orders
     * @param array<int, list<\stdClass>> $purchases
     */
    private function runExport(array $orders, array $purchases): string
    {
        $filename = 'export.csv';

        $this->buildHelper()->exportRun(
            $this->exportDir,
            $filename,
            $orders,
            $purchases,
            [],
            [],
            self::COLUMNS,
            ';',
            100,
            1
        );

        // exportRun дописує останню сторінку і перекодовує файл у Windows-1251.
        return mb_convert_encoding(file_get_contents($this->exportDir . $filename), 'UTF-8', 'Windows-1251');
    }

    private function buildHelper(): BackendOrdersExportHelper
    {
        $factory = $this->createMock(EntityFactory::class);
        $factory->method('get')->willReturnCallback(fn (string $class) => $this->createMock($class));

        $settings = $this->createMock(Settings::class);
        $settings->method('get')->willReturn(null);

        $modules = $this->createMock(Modules::class);
        $modules->method('isActiveModule')->willReturn(false);

        return new BackendOrdersExportHelper($factory, new RequestStub(true), $settings, $modules);
    }
}
