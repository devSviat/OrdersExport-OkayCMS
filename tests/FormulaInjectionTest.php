<?php

namespace Modules\Sviat\OrdersExport;

use Okay\Core\EntityFactory;
use Okay\Core\Modules\Modules;
use Okay\Core\Settings;
use Okay\Modules\Sviat\OrdersExport\Backend\Helpers\BackendOrdersExportHelper;
use Okay\Modules\Sviat\OrdersExport\Export\FormulaSafeCsv;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/RequestStub.php';

/**
 * Ім'я, коментар і адреса в замовленні вводить покупець. Комірка, що починається
 * з =, +, - або @, виконується в Excel і LibreOffice як формула.
 */
#[AllowMockObjectsWithoutExpectations]
class FormulaInjectionTest extends TestCase
{
    private const COLUMNS = [
        'order_id' => 'Номер замовлення',
        'name' => 'Покупець',
        'comment' => 'Коментар',
        'product_name' => 'Назва товару',
        'quantity' => 'Кількість',
        'price' => 'Ціна',
    ];

    private const HYPERLINK = '=HYPERLINK("http://x","y")';

    private string $exportDir;

    protected function setUp(): void
    {
        $this->exportDir = sys_get_temp_dir() . '/orders_export_formula_' . uniqid() . '/';
        mkdir($this->exportDir);
    }

    protected function tearDown(): void
    {
        array_map('unlink', glob($this->exportDir . '*') ?: []);
        rmdir($this->exportDir);
    }

    public function testBuyerNameAndCommentAreNeutralised(): void
    {
        $rows = $this->export([$this->order(['name' => self::HYPERLINK, 'comment' => '@SUM(1+1)'])]);

        $this->assertSame("'" . self::HYPERLINK, $rows[1][1]);
        $this->assertSame("'@SUM(1+1)", $rows[1][2]);
    }

    public function testControlOldWriterEmitsFormulaRaw(): void
    {
        $handle = fopen('php://temp', 'w+');
        fputcsv($handle, [self::HYPERLINK, '@SUM(1+1)'], ';', '"', '');
        rewind($handle);
        $row = fgetcsv($handle, 0, ';', '"', '');
        fclose($handle);

        $this->assertSame([self::HYPERLINK, '@SUM(1+1)'], $row);
    }

    public function testNoCellOfTheFileStartsWithAFormulaCharacter(): void
    {
        $rows = $this->export([$this->order([
            'name' => '-1+1',
            'comment' => "\t=1+1",
            'product_name' => '+cmd|x',
        ])]);

        foreach ($rows as $index => $row) {
            foreach ($row as $cell) {
                if ($index === 0 || is_numeric($cell)) {
                    continue;
                }
                $this->assertNotContains($cell[0] ?? '', ['=', '+', '-', '@', "\t", "\r"], $cell);
            }
        }
    }

    public function testAmountsStayNumbers(): void
    {
        $order = $this->order(['name' => 'Іван']);
        $purchase = (object) [
            'product_name' => 'Знижка',
            'sku' => 'D-1',
            'amount' => '1',
            'price' => '-123.45',
        ];

        $rows = $this->export([$order], [$order->id => [$purchase]]);

        $this->assertSame('-123.45', $rows[1][5]);
        $this->assertSame('1', $rows[1][4]);
        $this->assertSame((string) $order->id, $rows[1][0]);
    }

    public function testNumericPassThroughIsLimitedToRealNumbers(): void
    {
        $this->assertSame(-5, FormulaSafeCsv::escapeValue(-5));
        $this->assertSame(-1.5, FormulaSafeCsv::escapeValue(-1.5));
        $this->assertSame("'-5", FormulaSafeCsv::escapeValue('-5'));
        $this->assertSame(-123.45, FormulaSafeCsv::number('-123.45'));
        $this->assertSame(50, FormulaSafeCsv::number('50'));
        $this->assertSame('=1+1', FormulaSafeCsv::number('=1+1'));
        $this->assertSame('-1+1', FormulaSafeCsv::number('-1+1'));
        $this->assertSame('', FormulaSafeCsv::number(''));
    }

    public function testHeaderRowGoesThroughEscaping(): void
    {
        $handle = fopen('php://temp', 'w+');
        FormulaSafeCsv::putRow($handle, ['=x', 'ok'], ';');
        rewind($handle);
        $this->assertSame("'=x;ok\n", stream_get_contents($handle));
    }

    private function order(array $fields): \stdClass
    {
        return (object) ($fields + ['id' => 31, 'status_id' => 0, 'date' => '2026-08-18']);
    }

    /**
     * @param list<\stdClass> $orders
     * @param array<int, list<\stdClass>> $purchases
     * @return list<list<string>>
     */
    private function export(array $orders, array $purchases = []): array
    {
        $helper = $this->buildHelper();
        $filename = 'export.csv';

        $columns = self::COLUMNS;
        $filter = $helper->setUp($this->exportDir, $filename, $columns, ';', 100);
        $this->assertIsArray($filter);

        $helper->exportRun($this->exportDir, $filename, $orders, $purchases, [], [], $columns, ';', 100, 1);

        $csv = mb_convert_encoding(file_get_contents($this->exportDir . $filename), 'UTF-8', 'Windows-1251');
        $handle = fopen('php://temp', 'w+');
        fwrite($handle, $csv);
        rewind($handle);

        $rows = [];
        while (($row = fgetcsv($handle, 0, ';', '"', '')) !== false) {
            $rows[] = $row;
        }
        fclose($handle);

        return $rows;
    }

    private function buildHelper(): BackendOrdersExportHelper
    {
        $factory = $this->createStub(EntityFactory::class);
        $factory->method('get')->willReturnCallback(fn (string $class) => $this->createStub($class));

        $settings = $this->createStub(Settings::class);
        $settings->method('get')->willReturn(null);

        $modules = $this->createStub(Modules::class);
        $modules->method('isActiveModule')->willReturn(false);

        return new BackendOrdersExportHelper($factory, new RequestStub(true), $settings, $modules);
    }
}
