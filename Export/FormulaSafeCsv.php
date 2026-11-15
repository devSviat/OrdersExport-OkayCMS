<?php

namespace Okay\Modules\Sviat\OrdersExport\Export;

/**
 * Запис CSV без виконуваних формул (CWE-1236).
 *
 * Своя копія, а не Okay\Core\Export\CsvWriter: у стоковому OkayCMS 4.5.3 цього
 * класу немає, а модуль працює і там. Правило префіксів те саме.
 */
class FormulaSafeCsv
{
    private const array FORMULA_PREFIXES = ['=', '+', '-', '@', "\t", "\r"];

    /**
     * @param resource $handle
     * @param array<int|string, mixed> $values
     */
    public static function putRow($handle, array $values, string $delimiter = ';'): void
    {
        fputcsv($handle, array_map(self::escapeValue(...), $values), $delimiter, '"', '');
    }

    /**
     * int і float лишаються числами: від'ємна сума з апострофом стала б текстом
     * і випала із SUM. Рядок завжди вважається текстом, навіть якщо схожий на число.
     */
    public static function escapeValue(mixed $value): string|int|float
    {
        if (is_int($value) || is_float($value)) {
            return $value;
        }

        $value = (string) $value;
        if ($value !== '' && in_array($value[0], self::FORMULA_PREFIXES, true)) {
            return "'" . $value;
        }

        return $value;
    }

    /**
     * Приводить десяткове число з БД (PDO віддає рядки) до int або float, щоб
     * escapeValue не сприйняв його за текст. Усе інше повертає як є.
     */
    public static function number(mixed $value): mixed
    {
        if (is_string($value) && preg_match('/^-?\d+(\.\d+)?$/', $value) === 1) {
            return str_contains($value, '.') ? (float) $value : (int) $value;
        }

        return $value;
    }
}
