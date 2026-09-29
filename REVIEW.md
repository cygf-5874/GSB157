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
| D1 | 2 | Calendar.php::isLeap | 公历 `isLeap(1900)` 返回 true（1900 能被 100 整除、不能被 400 整除，应为 false）；`daysInMonth(1900,2)` 返回 29，`toJdn(1900,2,29)` 不抛 CalendarException，且与 `toJdn(1900,3,1)` 返回同一个 JDN 2415080。 |
| D2 | 3 | Calendar.php::assertValid | `toJdn(1582,10,5)` 到 `toJdn(1582,10,14)` 均返回整数 JDN（2299151…2299160）而不抛 CalendarException，改历缺失的十天被顺延补上；`fromJdn(2299160)` 还会还原出公历不存在的 1582-10-14。 |
| D3 | 4 | Calendar.php::isLeap | 儒略历下 `isLeap(1900, Calendar::JULIAN)` 返回 false（儒略历能被 4 整除即闰年，应为 true）；`daysInMonth(1900,2,Calendar::JULIAN)` 返回 28，`toJdn(1900,2,29,Calendar::JULIAN)` 抛 `Calconv\CalendarException`。 |
| D4 | 5 | Converter.php::toJdn | 负数（公元前）年份被向零截断：`floorDiv` 内 `intdiv(-1,400)` 得 0 而非向下取整的 -1；公历 `toJdn(0,1,1)` 返回 1721061（天文纪年公元前 1 年元旦应为 1721060），且 `toJdn(1,1,1)-toJdn(0,1,1)` 为 365，而该年按本库 `isLeap(0)` 是闰年（2 月 29 天，跨度应为 366）。 |
| D5 | 6 | Converter.php::toJdn | 返回语句用 `(int)(1.0 * $era * 146097)` 先算浮点再转整数；输入 (28000000000400, 3, 1) 时返回 10226790001867216，纯整数算式 `$era * 146097 + 1721120` 的结果是 10226790001867217，浮点取整丢掉 1 天，JDN 不是精确整数结果。 |
| D6 | 1、7 | Converter.php::fromJdn | 月循环始终按平年 2 月 28 天扣减：`fromJdn(toJdn(2024,3,1))` 返回 2024-03-02 而非 2024-03-01，3 月起差一天；`fromJdn(toJdn(2024,12,31))` 返回 2024-12-01，闰年 2 月 29 日往返后变成 2024-03-01。 |
