# 代码审查结论 · calconv

> 通读 `src/Calendar.php` 与 `src/Converter.php`，对照 README「对外保证」的 8 条逐处核对，
> 把下表的每一行填满。**源码一个字节都不许改**（`check/` 会校验 SHA-256）。

> - 「违反保证」写被违反的那一条的**编号**（1~8）。
> - 「文件::函数」写成 `文件名::函数名`（如 `Calendar.php::isLeap`）。
> - 「可判定证据」写一句能复现的话：给出输入形状能看出什么区别，或能说出抛出的异常类型。
>   措辞要具体、可核对。

## 结论清单

| 编号 | 违反保证 | 文件::函数 | 可判定证据 |
| --- | --- | --- | --- |
| D1 | 2 | Calendar.php::isLeap | `Calendar::isLeap(1900)`（默认公历）返回 `true`，按公历闰年规则应返回 `false`（1900 能被 100 整除、不能被 400 整除）；连带 `Converter::toJdn(1900, 2, 29)` 不抛 `CalendarException` 而返回整数 2415080，平年 2 月 29 日被当成合法日期。 |
| D2 | 3 | Calendar.php::assertValid | `Converter::toJdn(1582, 10, 5)` 与 `Converter::toJdn(1582, 10, 14)`（公历）均不抛 `CalendarException`，分别返回 2299151 与 2299160，`Converter::fromJdn(2299160)` 返回 1582-10-14；按保证 3，改历空档 1582-10-05 至 1582-10-14 在公历中不存在，输入时必须抛 `CalendarException`，不得顺延换算。 |
| D3 | 4 | Calendar.php::isLeap | `Calendar::isLeap(1900, Calendar::JULIAN)` 返回 `false`，按儒略历“能被 4 整除即闰年”应返回 `true`；`Calendar::daysInMonth(1900, 2, Calendar::JULIAN)` 因此返回 28（应为 29）。 |
| D4 | 5 | Converter.php::floorDiv | `floorDiv` 直接用 `intdiv`：`intdiv(-5, 4)` 返回 -1（向零截断），保证 5 要求负数整除向下取整为 -2；可观测后果是 `Converter::toJdn(-5, 1, 1, Calendar::JULIAN)` 返回 1719233，比正确 JDN 1719232 大 1，公历 `Converter::toJdn(-5, 1, 1)` 返回 1719235（正确值 1719234）。 |
| D5 | 6 | Converter.php::toJdn | `toJdn` 的中间量 `(153 * $m + 2) / 5` 是 float（PHP 的 `/` 在非整除时返回 double），公历返回值又写成 `(int)(1.0 * $era * 146097)` 走浮点；`Converter::toJdn(24800000000000, 1, 1)` 返回 9058014001721061，同一公式的纯整数结果为 9058014001721060，大年份因 double 53 位尾数丢 1。 |
| D6 | 1、7 | Converter.php::fromJdn | 公历闰年往返失败：`Converter::fromJdn(Converter::toJdn(2024, 2, 29))` 中 `toJdn` 得 2460370，`fromJdn(2460370)` 返回 2024-03-01（应还原 2024-02-29）；`Converter::fromJdn(Converter::toJdn(2024, 3, 1))` 返回 2024-03-02，闰年 3 月起整体差一天。根因是它遍历平年表 `MONTH_DAYS`，闰年 2 月未按 29 天处理。 |
