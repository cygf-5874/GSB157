历法换算的边缘规则需要先确认。calconv 是 PHP 8 的儒略日换算库（php-cli，无 composer，无第三方依赖），自检走 scripts/check.sh。构建不需要额外步骤；`check/` 是固定验收程序，别改，既有用例走 `php tests/run.php`。

`src/Calendar.php` 与 `src/Converter.php` 就是要接手的这份实现，README 的「对外保证」8 条是它的契约。

任务：通读这两个文件，把 `REVIEW.md` 里那张表填满 —— 每个问题一行，写清它违反哪一条保证、出在哪个文件的哪个函数、以及一句可判定的证据（别人照你说的输入能看出区别，或者能说出抛的异常类型）。

验收：
- php check/check.php 退出码 0，8 个场景全过（unmodified 1 + format 1 + finding 6）；
- 两个源码文件一个字节都不许改，`check/` 会校验 SHA-256。

约束：
1. 只改 `REVIEW.md`；`src/` 与 `tests/` 下的文件一律不动。
2. 不改 `check/`。
3. 不许用 composer，不许引入任何第三方库；不许用 mbstring 扩展。
4. 证据一列不要写「看起来不对」「可能有风险」这类话。