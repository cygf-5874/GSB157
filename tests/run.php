<?php

declare(strict_types=1);

require __DIR__ . '/../autoload.php';

use Calconv\Calendar;
use Calconv\CalendarException;
use Calconv\Converter;

/**
 * 既有用例：只覆盖 1900–2100 之间的常规日期与常规闰年。
 */
final class CaseFailure extends \Exception
{
    public function __construct(
        public readonly string $expected,
        public readonly string $actual
    ) {
        parent::__construct('assertion failed');
    }
}

function show(mixed $value): string
{
    $text = is_string($value) ? $value : var_export($value, true);

    return str_replace(["\n", "\r", "\t"], ['\n', '\r', '\t'], $text);
}

function expectSame(mixed $expected, mixed $actual): void
{
    if ($expected !== $actual) {
        throw new CaseFailure(show($expected), show($actual));
    }
}

function expectTrue(bool $ok, string $expected, string $actual): void
{
    if (!$ok) {
        throw new CaseFailure($expected, $actual);
    }
}

function expectThrows(callable $fn, string $class): void
{
    try {
        $fn();
    } catch (\Throwable $error) {
        if ($error instanceof $class) {
            return;
        }
        throw new CaseFailure($class, get_class($error));
    }

    throw new CaseFailure($class, '(未抛异常)');
}

function parts(array $date): string
{
    return sprintf('%d-%02d-%02d', $date['year'], $date['month'], $date['day']);
}

$cases = [];

$cases[] = [
    '公历 JDN：2000-01-01 是 2451545',
    static function (): void {
        expectSame(2451545, Converter::toJdn(2000, 1, 1));
    },
];

$cases[] = [
    '公历 JDN：1970-01-01 是 2440588',
    static function (): void {
        expectSame(2440588, Converter::toJdn(1970, 1, 1));
    },
];

$cases[] = [
    '公历 JDN：1582-10-15 是 2299161（改历之后的第一天）',
    static function (): void {
        expectSame(2299161, Converter::toJdn(1582, 10, 15));
    },
];

$cases[] = [
    '公历 JDN：2023-06-15 是 2460111',
    static function (): void {
        expectSame(2460111, Converter::toJdn(2023, 6, 15));
    },
];

$cases[] = [
    'fromJdn：2451545 回到 2000-01-01',
    static function (): void {
        expectSame('2000-01-01', parts(Converter::fromJdn(2451545)));
    },
];

$cases[] = [
    'fromJdn：2440588 回到 1970-01-01',
    static function (): void {
        expectSame('1970-01-01', parts(Converter::fromJdn(2440588)));
    },
];

$cases[] = [
    '公历往返：2023-06-15',
    static function (): void {
        $jdn = Converter::toJdn(2023, 6, 15);
        expectSame('2023-06-15', parts(Converter::fromJdn($jdn)));
    },
];

$cases[] = [
    '公历往返：1999-03-01',
    static function (): void {
        $jdn = Converter::toJdn(1999, 3, 1);
        expectSame('1999-03-01', parts(Converter::fromJdn($jdn)));
    },
];

$cases[] = [
    '公历往返：闰年年初（2024-02-20）',
    static function (): void {
        $jdn = Converter::toJdn(2024, 2, 20);
        expectSame('2024-02-20', parts(Converter::fromJdn($jdn)));
    },
];

$cases[] = [
    '公历往返：年末（2023-12-31）',
    static function (): void {
        $jdn = Converter::toJdn(2023, 12, 31);
        expectSame('2023-12-31', parts(Converter::fromJdn($jdn)));
    },
];

$cases[] = [
    '儒略历 JDN：2000-01-01 是 2451558（比公历晚 13 天）',
    static function (): void {
        expectSame(2451558, Converter::toJdn(2000, 1, 1, Calendar::JULIAN));
    },
];

$cases[] = [
    '儒略历往返：2023-06-15',
    static function (): void {
        $jdn = Converter::toJdn(2023, 6, 15, Calendar::JULIAN);
        expectSame('2023-06-15', parts(Converter::fromJdn($jdn, Calendar::JULIAN)));
    },
];

$cases[] = [
    '闰年判定：2024 是闰年、2023 不是（公历）',
    static function (): void {
        expectSame(true, Calendar::isLeap(2024, Calendar::GREGORIAN));
        expectSame(false, Calendar::isLeap(2023, Calendar::GREGORIAN));
    },
];

$cases[] = [
    '闰年判定：儒略历下 2024 是闰年、2023 不是',
    static function (): void {
        expectSame(true, Calendar::isLeap(2024, Calendar::JULIAN));
        expectSame(false, Calendar::isLeap(2023, Calendar::JULIAN));
    },
];

$cases[] = [
    '每月天数：2024-02 是 29、2023-02 是 28、2023-04 是 30',
    static function (): void {
        expectSame(29, Calendar::daysInMonth(2024, 2));
        expectSame(28, Calendar::daysInMonth(2023, 2));
        expectSame(30, Calendar::daysInMonth(2023, 4));
    },
];

$cases[] = [
    '非法日期：2023-02-29 抛 CalendarException',
    static function (): void {
        expectThrows(
            static fn () => Converter::toJdn(2023, 2, 29),
            CalendarException::class
        );
    },
];

$cases[] = [
    '合法日期：2024-02-29 可以换算',
    static function (): void {
        expectTrue(Converter::toJdn(2024, 2, 29) > 0, '大于 0', '非正数');
    },
];

$cases[] = [
    '非法月份：2023-13-01 抛 CalendarException',
    static function (): void {
        expectThrows(
            static fn () => Converter::toJdn(2023, 13, 1),
            CalendarException::class
        );
    },
];

$passed = 0;
$total = count($cases);

foreach ($cases as $index => $case) {
    $number = str_pad((string) ($index + 1), 2, '0', STR_PAD_LEFT);
    $name = $case[0];

    try {
        $case[1]();
        $passed++;
        echo "PASS  $number $name\n";
    } catch (\Throwable $error) {
        $expected = $error instanceof CaseFailure ? $error->expected : '(无异常)';
        $actual = $error instanceof CaseFailure ? $error->actual : $error->getMessage();
        echo "FAIL  $number $name  期望=$expected 实际=$actual\n";
    }
}

echo "通过 $passed/$total\n";

exit($passed === $total ? 0 : 1);