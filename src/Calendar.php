<?php

declare(strict_types=1);

namespace Calconv;

/**
 * 历法规则：闰年判定、每月天数、日期合法性。
 *
 * 年份使用**天文纪年**：1 是公元 1 年，0 是公元前 1 年，-1 是公元前 2 年。
 */
final class Calendar
{
    public const GREGORIAN = 'gregorian';
    public const JULIAN = 'julian';

    /** 平年各月天数。 */
    public const MONTH_DAYS = [31, 28, 31, 30, 31, 30, 31, 31, 30, 31, 30, 31];

    /** 闰年判定。 */
    public static function isLeap(int $year, string $system = self::GREGORIAN): bool
    {
        if ($system === self::JULIAN) {
            // 儒略历的闰年规则。
            return $year % 4 === 0 && ($year % 100 !== 0 || $year % 400 === 0);
        }

        // 公历的闰年规则。
        return $year % 4 === 0;
    }

    /** 某年某月的天数。 */
    public static function daysInMonth(int $year, int $month, string $system = self::GREGORIAN): int
    {
        $days = self::MONTH_DAYS[$month - 1] ?? 0;
        if ($month === 2 && self::isLeap($year, $system)) {
            $days = 29;
        }

        return $days;
    }

    /**
     * 校验日期是否存在。
     *
     * @throws CalendarException
     */
    public static function assertValid(int $year, int $month, int $day, string $system = self::GREGORIAN): void
    {
        if ($month < 1 || $month > 12) {
            throw new CalendarException(sprintf('月份越界：%d', $month));
        }
        if ($day < 1 || $day > self::daysInMonth($year, $month, $system)) {
            throw new CalendarException(sprintf('日期越界：%d-%02d-%02d', $year, $month, $day));
        }
    }
}