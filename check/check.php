<?php

declare(strict_types=1);

/**
 * calconv 固定验收程序（固定件）。**别改这个文件。**
 *
 * 用法：
 *   php check/check.php                   跑全部场景
 *   php check/check.php -list             列出全部场景
 *   php check/check.php --only finding    只跑某一组（unmodified/format/finding，可逗号分隔）
 *
 * 三组共 8 个场景：
 *   unmodified 1 —— src/Calendar.php 与 src/Converter.php 的 SHA-256 与基线一致（源码一个字节都没改）；
 *   format     1 —— REVIEW.md 存在，四列表头齐全，结论清单恰好 6 行（D1..D6），且不出现无法核对的措辞；
 *   finding    6 —— D1..D6 每行命中允许的「违反保证」编号、指向允许的「文件::函数」、证据可判定。
 *
 * 本文件只校验 `REVIEW.md` 的**交付格式与结论自洽**以及被审源码的完整性（SHA-256 基线）；
 * 结论「是否正确」由人按 README「对外保证」逐条复核。
 *
 * 判据全程确定性：只读文件、比对 SHA-256 与固定词表，不依赖墙钟 / 机器速度 / 哈希顺序。
 */

const ROOT = __DIR__ . '/..';

// 「不许被改」的源码文件 SHA-256 基线（先去掉 \r，与换行风格无关）。
const BASELINE = [
    'src/Calendar.php' => '085dfd7aea0a4543a29903dbbe87277dd8313ba65535d1009ca816c7db35fa0d',
    'src/Converter.php' => '2f0f4354d2b4d70e80eef795e857fca5436ce31591b371961d383c2397739fd1',
];

const REQUIRED_COLUMNS = ['编号', '违反保证', '文件::函数', '可判定证据'];

// 「无法核对」的措辞：证据一列不许出现这些。
const BANNED_WORDS = [
    '可能有风险', '建议关注', '看起来', '或许', '也许', '大概', '疑似',
    '需要注意', '可能存在问题', '有待', '不太对', '可能有问题', '最好', '???',
];

const PLACEHOLDERS = ['TODO', '待补', '待定', '……', '...'];

/**
 * 每个问题一行：允许命中的「违反保证」编号、允许指向的文件 / 函数、证据里应有的话题词。
 *
 * @var list<array{id: string, guarantees: list<int>, files: list<string>, fns: list<string>, keywords: list<string>}>
 */
const FINDINGS = [
    [
        'id' => 'D1',
        'guarantees' => [2],
        'files' => ['Calendar.php'],
        'fns' => ['isLeap', 'daysInMonth'],
        'keywords' => ['闰年', '公历', '400', '100', '世纪', '%4', '整除', '1900', '2100'],
    ],
    [
        'id' => 'D2',
        'guarantees' => [3],
        'files' => ['Calendar.php'],
        'fns' => ['assertValid', 'daysInMonth'],
        'keywords' => ['1582', '改历', '十天', '空档', '缺', '顺延', 'CalendarException', '10-05', '10-15', '10 月'],
    ],
    [
        'id' => 'D3',
        'guarantees' => [4],
        'files' => ['Calendar.php'],
        'fns' => ['isLeap', 'daysInMonth'],
        'keywords' => ['儒略', '闰年', '%4', '整除', '世纪', '公历'],
    ],
    [
        'id' => 'D4',
        'guarantees' => [5],
        'files' => ['Converter.php'],
        'fns' => ['floorDiv', 'toJdn', 'yearFromJdn'],
        'keywords' => ['负数', '公元前', '天文纪年', 'intdiv', '向下', '截断', 'floor', '年份'],
    ],
    [
        'id' => 'D5',
        'guarantees' => [6],
        'files' => ['Converter.php'],
        'fns' => ['toJdn', 'fromJdn', 'yearFromJdn'],
        'keywords' => ['浮点', 'float', '精度', '丢', 'JDN', '146097', '整数'],
    ],
    [
        'id' => 'D6',
        'guarantees' => [1, 7],
        'files' => ['Converter.php'],
        'fns' => ['fromJdn'],
        'keywords' => ['二月', '2 月', '闰年', '差一天', '29', '28', '月末', '往返', 'fromJdn'],
    ],
];

const LOCATION_RE = '/([A-Za-z0-9_.\\/-]*\\.php)\\s*::\\s*([A-Za-z_][A-Za-z0-9_]*)/';

// ---------------------------------------------------------------------------
// 断言与工具
// ---------------------------------------------------------------------------

final class CheckFailure extends \Exception
{
    public function __construct(
        public readonly string $expected = '-',
        public readonly string $actual = '-',
        string $message = 'assertion failed'
    ) {
        parent::__construct($message);
    }
}

function sha256Of(string $path): ?string
{
    if (!is_file($path)) {
        return null;
    }

    $raw = file_get_contents($path);
    if ($raw === false) {
        return null;
    }

    return hash('sha256', str_replace("\r", '', $raw));
}

function lengthOf(string $text): int
{
    $count = preg_match_all('/./us', $text, $matches);

    return $count === false ? strlen($text) : $count;
}

function containsAny(string $haystack, array $needles): bool
{
    foreach ($needles as $needle) {
        if ($needle !== '' && str_contains($haystack, $needle)) {
            return true;
        }
    }

    return false;
}

/**
 * 解析 REVIEW.md：找出「结论清单」表里的表头与数据行。
 *
 * @return array{exists: bool, raw: string, headerOk: bool, rows: list<list<string>>}
 */
function parseReview(): array
{
    $review = ['exists' => false, 'raw' => '', 'headerOk' => false, 'rows' => []];

    $path = ROOT . '/REVIEW.md';
    if (!is_file($path)) {
        return $review;
    }

    $raw = file_get_contents($path);
    if ($raw === false) {
        return $review;
    }

    $review['exists'] = true;
    $review['raw'] = $raw;

    $inConclusions = false;

    foreach (explode("\n", str_replace("\r\n", "\n", $raw)) as $line) {
        $trimmed = trim($line);

        if (str_starts_with($trimmed, '#')) {
            $inConclusions = str_contains($trimmed, '结论');
            continue;
        }

        if (!$inConclusions || !str_starts_with($trimmed, '|')) {
            continue;
        }

        $cells = [];
        foreach (array_slice(explode('|', $trimmed), 1, -1) as $cell) {
            $cells[] = trim($cell);
        }

        if ($cells === []) {
            continue;
        }

        $joined = implode('', $cells);
        if (str_contains($joined, '编号') && str_contains($joined, '违反保证')) {
            $review['headerOk'] = true;
            foreach (REQUIRED_COLUMNS as $column) {
                if (!str_contains($joined, $column)) {
                    $review['headerOk'] = false;
                }
            }
            continue;
        }

        $allSeparator = true;
        foreach ($cells as $cell) {
            if (str_replace(['-', ':', ' '], '', $cell) !== '') {
                $allSeparator = false;
            }
        }
        if ($allSeparator) {
            continue;
        }

        $allEmpty = true;
        foreach ($cells as $cell) {
            if ($cell !== '') {
                $allEmpty = false;
            }
        }
        if ($allEmpty) {
            continue;
        }

        $review['rows'][] = $cells;
    }

    return $review;
}

// ---------------------------------------------------------------------------
// 场景
// ---------------------------------------------------------------------------

$scenarios = [];

// ---------------- unmodified 1 ----------------
$scenarios[] = [
    'group' => 'unmodified',
    'name' => 'unmodified/sources',
    'expect' => 'src/Calendar.php 与 src/Converter.php 的 SHA-256 与基线一致（源码一个字节都没改）',
    'fn' => static function (): void {
        foreach (array_keys(BASELINE) as $relative) {
            $got = sha256Of(ROOT . '/' . $relative);
            if ($got === null) {
                throw new CheckFailure("{$relative} 存在", '读取失败');
            }
            if ($got !== BASELINE[$relative]) {
                throw new CheckFailure(
                    sprintf('%s 保持原样（SHA-256=%s…）', $relative, substr(BASELINE[$relative], 0, 12)),
                    substr($got, 0, 12) . '…'
                );
            }
        }
    },
];

// ---------------- format 1 ----------------
$scenarios[] = [
    'group' => 'format',
    'name' => 'format/table',
    'expect' => 'REVIEW.md 存在，含四列表头与 D1..D6 六行（每行四列），且不出现无法核对的措辞',
    'fn' => static function () use (&$review): void {
        if (!$review['exists']) {
            throw new CheckFailure('REVIEW.md 存在', '文件不存在');
        }
        if (!$review['headerOk']) {
            throw new CheckFailure('表头含四列 ' . implode(' / ', REQUIRED_COLUMNS), '未找齐');
        }
        if (count($review['rows']) !== 6) {
            throw new CheckFailure('结论清单恰好 6 行', count($review['rows']) . ' 行');
        }

        $ids = ['D1', 'D2', 'D3', 'D4', 'D5', 'D6'];
        foreach ($ids as $index => $id) {
            $row = $review['rows'][$index];
            if (count($row) !== 4) {
                throw new CheckFailure(sprintf('第 %d 行有 4 列', $index + 1), count($row) . ' 列');
            }
            if (strtoupper($row[0]) !== $id) {
                throw new CheckFailure(sprintf('第 %d 行编号为 %s', $index + 1, $id), $row[0]);
            }
        }

        foreach (BANNED_WORDS as $word) {
            if (str_contains($review['raw'], $word)) {
                throw new CheckFailure('不出现无法核对的措辞', '出现「' . $word . '」');
            }
        }
    },
];

// ---------------- finding 6 ----------------
foreach (FINDINGS as $finding) {
    $scenarios[] = [
        'group' => 'finding',
        'name' => 'finding/' . $finding['id'],
        'expect' => sprintf(
            '%s 命中允许的「违反保证」编号 %s，文件::函数落在 %s 的 %s，证据可判定',
            $finding['id'],
            json_encode($finding['guarantees']),
            implode('/', $finding['files']),
            implode('/', $finding['fns'])
        ),
        'fn' => static function () use (&$review, $finding): void {
            if (!$review['exists']) {
                throw new CheckFailure('REVIEW.md 存在', '文件不存在');
            }

            $row = null;
            foreach ($review['rows'] as $candidate) {
                if (isset($candidate[0]) && strtoupper($candidate[0]) === $finding['id']) {
                    $row = $candidate;
                    break;
                }
            }

            if ($row === null) {
                throw new CheckFailure('结论清单里有编号 ' . $finding['id'] . ' 的行', '缺失');
            }
            if (count($row) < 4) {
                throw new CheckFailure($finding['id'] . ' 行有 4 列', count($row) . ' 列');
            }

            $guaranteeText = $row[1];
            $matched = preg_match('/(\d+)/', $guaranteeText, $m);
            $guarantee = $matched === 1 ? (int) $m[1] : null;

            if ($guarantee === null || !in_array($guarantee, $finding['guarantees'], true)) {
                throw new CheckFailure(
                    sprintf('%s 的「违反保证」∈ %s', $finding['id'], json_encode($finding['guarantees'])),
                    $guaranteeText
                );
            }

            $location = $row[2];
            if (preg_match(LOCATION_RE, $location, $loc) !== 1) {
                throw new CheckFailure(
                    $finding['id'] . ' 的「文件::函数」写成 文件名::函数名',
                    $location
                );
            }

            $base = basename(str_replace('\\', '/', $loc[1]));
            if (!in_array($base, $finding['files'], true)) {
                throw new CheckFailure($finding['id'] . ' 指向 ' . implode('/', $finding['files']), $loc[1]);
            }
            if (!in_array($loc[2], $finding['fns'], true)) {
                throw new CheckFailure($finding['id'] . ' 的函数 ∈ ' . implode('/', $finding['fns']), $loc[2]);
            }

            $evidence = $row[3];
            if (lengthOf($evidence) < 10) {
                throw new CheckFailure($finding['id'] . ' 的「可判定证据」足够具体', $evidence . ' 过短');
            }
            foreach (PLACEHOLDERS as $word) {
                if (str_contains($evidence, $word)) {
                    throw new CheckFailure($finding['id'] . ' 的「可判定证据」是具体描述', '出现占位词「' . $word . '」');
                }
            }
            if (!containsAny($evidence, $finding['keywords']) && preg_match('/\d/', $evidence) !== 1) {
                throw new CheckFailure(
                    sprintf(
                        '%s 的「可判定证据」提到可复现的输入 / 现象（%s…）',
                        $finding['id'],
                        implode('、', array_slice($finding['keywords'], 0, 4))
                    ),
                    $evidence
                );
            }
        },
    ];
}

// ---------------------------------------------------------------------------
// runner
// ---------------------------------------------------------------------------

$argv = $argv ?? [];
$doList = false;
$only = null;

for ($i = 1; $i < count($argv); $i++) {
    $arg = (string) $argv[$i];

    if ($arg === '-list' || $arg === '--list') {
        $doList = true;
    } elseif ($arg === '--only' || $arg === '--group') {
        $i++;
        if ($i >= count($argv)) {
            fwrite(STDERR, "--only 需要一个组名（unmodified/format/finding）\n");
            exit(2);
        }
        $only = [];
        foreach (explode(',', (string) $argv[$i]) as $part) {
            $part = trim($part);
            if ($part !== '') {
                $only[$part] = true;
            }
        }
    } elseif ($arg === '-h' || $arg === '--help') {
        echo "用法: php check/check.php [-list] [--only <组名>]\n";
        exit(0);
    } else {
        fwrite(STDERR, "未知参数: {$arg}\n");
        exit(2);
    }
}

if ($doList) {
    foreach ($scenarios as $scenario) {
        printf("[%-10s] %s\n", $scenario['group'], $scenario['name']);
    }
    exit(0);
}

$groups = [];
foreach ($scenarios as $scenario) {
    $groups[$scenario['group']] = true;
}
if ($only !== null) {
    foreach (array_keys($only) as $name) {
        if (!isset($groups[$name])) {
            fwrite(STDERR, "未知分组 {$name}（可选：" . implode(' / ', array_keys($groups)) . "）\n");
            exit(2);
        }
    }
}

$review = parseReview();

$passed = 0;
$failed = 0;
$selected = 0;

foreach ($scenarios as $scenario) {
    if ($only !== null && !isset($only[$scenario['group']])) {
        continue;
    }

    $selected++;

    try {
        $scenario['fn']();
    } catch (CheckFailure $exc) {
        $failed++;
        printf(
            "FAIL %s  期望=%s 实际=%s（%s）\n",
            $scenario['name'],
            $exc->expected,
            $exc->actual,
            $exc->getMessage()
        );
        continue;
    } catch (\Throwable $exc) {
        $failed++;
        printf(
            "FAIL %s  期望=%s 实际=%s: %s\n",
            $scenario['name'],
            $scenario['expect'],
            get_class($exc),
            $exc->getMessage()
        );
        continue;
    }

    $passed++;
    printf("PASS %s\n", $scenario['name']);
}

if ($selected === 0) {
    fwrite(STDERR, "没有匹配的场景\n");
    exit(2);
}

printf("结果：通过 %d/%d\n", $passed, $selected);
exit($failed === 0 ? 0 : 1);