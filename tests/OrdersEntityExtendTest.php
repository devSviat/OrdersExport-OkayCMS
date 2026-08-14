<?php

namespace Modules\Sviat\OrdersExport;

use Okay\Core\QueryFactory\Select;
use Okay\Modules\Sviat\NovaPoshtaTracking\Entities\NovaPoshtaTrackingEntity;
use Okay\Modules\Sviat\OrdersExport\ExtendsEntities\OrdersEntityExtend;
use PHPUnit\Framework\TestCase;

// Сусідній модуль необов'язковий: у прогоні релізу поруч лише цей, і справжньої
// сутності немає. Свій require, бо на порядок завантаження файлів у теці
// покладатися не можна.
if (!class_exists(NovaPoshtaTrackingEntity::class)) {
    require_once __DIR__ . '/NovaPoshtaTrackingEntityStub.php';
}

/**
 * Фільтр has_ttn спирається на сутність сусіднього модуля NovaPoshtaTracking,
 * а той необов'язковий.
 */
class OrdersEntityExtendTest extends TestCase
{
    /** @var array<int, array{string, array}> */
    private $calls = [];

    private function makeFilter(bool $trackingModule = true): OrdersEntityExtend
    {
        $this->calls = [];
        $record = function (string $method) {
            return function (...$args) use ($method) {
                $this->calls[] = [$method, $args];
            };
        };

        $select = $this->createMock(Select::class);
        foreach (['join', 'where', 'groupBy', 'bindValue'] as $method) {
            $select->method($method)->willReturnCallback($record($method));
        }

        // Підміняється й гілка «модуль є»: справжній hasTrackingModule() пішов би
        // в контейнер по Modules, а модульний тест рушія не піднімає.
        $filter = $trackingModule
            ? new class extends OrdersEntityExtend {
                protected function hasTrackingModule(): bool
                {
                    return true;
                }
            }
            : new class extends OrdersEntityExtend {
                protected function hasTrackingModule(): bool
                {
                    return false;
                }
            };
        $filter->setSelect($select);

        return $filter;
    }

    /** @return list<string> */
    private function methodsCalled(): array
    {
        return array_column($this->calls, 0);
    }

    private function firstArgs(string $method): array
    {
        foreach ($this->calls as [$name, $args]) {
            if ($name === $method) {
                return $args;
            }
        }

        return [];
    }

    public function testFilterIsSkippedWhenNotRequested(): void
    {
        $filter = $this->makeFilter();
        $filter->filter__has_ttn(false, []);
        $filter->filter__has_ttn(null, []);
        $filter->filter__has_ttn('0', []);

        self::assertSame([], $this->methodsCalled(), 'без запиту фільтр не має чіпати запит');
    }

    public function testJoinsTrackingTableWhenModuleIsPresent(): void
    {
        $filter = $this->makeFilter();
        $filter->filter__has_ttn(true, []);

        self::assertContains('join', $this->methodsCalled());
        self::assertStringContainsString('npt_has_ttn', $this->firstArgs('join')[1]);
    }

    /**
     * Регресія: раніше тут був фатал — звернення до статичного методу класу,
     * якого в рушії немає.
     */
    public function testYieldsNothingWhenModuleIsAbsent(): void
    {
        $filter = $this->makeFilter(false);
        $filter->filter__has_ttn(true, []);

        self::assertNotContains('join', $this->methodsCalled(), 'приєднувати нічого');
        // Лише перша умова: where() варіативний, і PHPUnit 13 дописує моку
        // порожній масив зв'язувань, а PHPUnit 9 — ні.
        self::assertSame('1 = 0', $this->firstArgs('where')[0] ?? null, 'результат має бути порожній');
    }
}
