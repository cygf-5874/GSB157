<?php

declare(strict_types=1);

namespace Calconv;

/**
 * 儒略日数（JDN）与公历 / 儒略历日期之间的换算。
 */
final class Converter
{
    /**
     * 把某个历法的日期换算成儒略日数。
     */
    public static function toJdn(int $year, int $month, int $day, string $system = Calendar::GREGORIAN): int
    {
        Calendar::assertValid($year, $month, $day, $system);

        $shifted = $month <= 2 ? $year - 1 : $year;
        $m = $month > 2 ? $month - 3 : $month + 9;
        $doy = (int) ((153 * $m + 2) / 5) + $day - 1;

        if ($system === Calendar::JULIAN) {
            return 1721118 + 365 * $shifted + self::floorDiv($shifted, 4) + $doy;
        }

        $era = self::floorDiv($shifted, 400);
        $yoe = $shifted - $era * 400;
        $doe = $yoe * 365 + self::floorDiv($yoe, 4) - self::floorDiv($yoe, 100)
            + self::floorDiv($yoe, 400) + $doy;

        return (int) (1.0 * $era * 146097) + $doe + 1721120;
    }

    /**
     * 把儒略日数换算回某个历法的日期。
     *
     * @return array{year: int, month: int, day: int}
     */
    public static function fromJdn(int $jdn, string $system = Calendar::GREGORIAN): array
    {
        $year = self::yearFromJdn($jdn, $system);
        $start = self::toJdn($year, 1, 1, $system);
        $dayOfYear = $jdn - $start;

        $month = 12;
        foreach (Calendar::MONTH_DAYS as $index => $length) {
            if ($dayOfYear < $length) {
                $month = $index + 1;
                break;
            }
            $dayOfYear -= $length;
        }

        return ['year' => $year, 'month' => $month, 'day' => $dayOfYear + 1];
    }

    /**
     * 求出 `$jdn` 落在哪一个天文纪年。
     */
    public static function yearFromJdn(int $jdn, string $system = Calendar::GREGORIAN): int
    {
        $base = $system === Calendar::JULIAN ? 1721118 : 1721120;
        $year = intdiv($jdn - $base, 365);

        while (self::toJdn($year + 1, 1, 1, $system) <= $jdn) {
            $year++;
        }
        while (self::toJdn($year, 1, 1, $system) > $jdn) {
            $year--;
        }

        return $year;
    }

    private static function floorDiv(int $a, int $b): int
    {
        return intdiv($a, $b);
    }
}