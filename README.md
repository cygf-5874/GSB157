# Calconv —— 儒略日换算

PHP 8 的历法换算库：在**儒略日数（JDN）**与**公历 / 儒略历**日期之间互相换算。
只依赖 PHP 标准库（php-cli），不用 composer、不用第三方依赖、不用 mbstring（字符串与数值全程按字节 / 整数处理）。

## 用法

```php
require __DIR__ . '/autoload.php';

use Calconv\Calendar;
use Calconv\Converter;

Converter::toJdn(2000, 1, 1);                 // => 2451545（公历）
Converter::fromJdn(2451545);                  // => ['year' => 2000, 'month' => 1, 'day' => 1]
Converter::toJdn(2000, 1, 1, Calendar::JULIAN);  // => 2451558（儒略历）
```

## 对外保证

以下 8 条是这份实现的契约，也是评审的依据。

1. **往返等价**：对合法日期，`fromJdn(toJdn(y, m, d, sys), sys)` 必须还原成 `(y, m, d)`；
   对合法 `jdn`，`toJdn(fromJdn(jdn, sys) 的各分量, sys)` 必须还原成 `jdn`（同一历法内）。
2. **公历闰年**：能被 4 整除、但不能被 100 整除；能被 400 整除的仍算闰年。
3. **公历改历边界**：1582-10-04 的下一天是 1582-10-15；1582-10-05 到 1582-10-14 这十天
   **在公历里不存在**，作为输入时必须抛 `CalendarException`，不得顺延成合法日期。
4. **儒略历闰年**：能被 4 整除即为闰年。
5. **年份表示**：年份采用**天文纪年**——`1` 是公元 1 年，`0` 是公元前 1 年，`-1` 是公元前 2 年；
   负数年份的整除一律**向下取整**（向负无穷），不得向零截断。
6. **全整数**：`toJdn` / `fromJdn` / `yearFromJdn` 的输入、输出与中间计算**不得出现浮点**；
   换算结果必须是精确整数。
7. **边界**：月末最后一天（含闰年 2 月 29 日）、年末最后一天要能正确换算与还原；
   越界日期（如平年 2 月 29 日、13 月）抛 `CalendarException`。
8. **纯函数、确定性**：`Calendar` 与 `Converter` 的公开方法不修改任何状态，也不依赖全局状态
   （不含 `setlocale`、不读系统时区）；同一输入多次调用结果完全相同。

## 本次交付物

`REVIEW.md`：对照上面 8 条保证通读 `src/` 两个文件，把「结论清单」表填满 ——
每个问题一行，写清它违反哪条保证、出在哪个文件::函数、以及一句可复核的证据。
`src/`、`tests/`、`check/` 一律不改。

## 目录

- `src/Calendar.php` —— 闰年判定、每月天数、日期合法性
- `src/Converter.php` —— `toJdn` / `fromJdn` / `yearFromJdn`
- `src/CalendarException.php` —— 异常类型
- `autoload.php` —— `spl_autoload_register` 加载 `src/` 下的类
- `tests/run.php` —— 既有回归用例
- `check/check.php` —— 固定验收（**勿改**）
- `scripts/check.sh` —— 固定验收入口
- `REVIEW.md` —— 本次要交付的评审结论

## 自检

```bash
php tests/run.php
bash scripts/check.sh            # = php check/check.php
bash scripts/check.sh -list
bash scripts/check.sh --only finding
```

`check/check.php` 支持 `-list` 与 `--only <组名>`（组名：`unmodified` / `format` / `finding`），
逐场景打印 `PASS <组>/<名>` 或 `FAIL <组>/<名>  期望=… 实际=…`，结尾打印 `结果：通过 x/N`，
全过 `exit 0`，否则 `exit 1`，失败不早退。它同时校验 `src/` 下两个源码文件的 SHA-256。

## 环境

PHP 8（php-cli）。不需要 composer，不使用 mbstring。