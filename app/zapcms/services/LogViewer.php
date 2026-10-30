<?php

namespace zapcms\services;

/**
 * 运行日志读取器。
 *
 * 日志由 SimpleLogger 写入，格式：
 *   [2026-10-30T08:34:34.762886+08:00] app.WARNING: Deprecated: ... {"file":"...","line":23} []
 *
 * 大文件只读取尾部若干行，避免一次性把整个日志载入内存。
 */
class LogViewer
{
    /**
     * 单次最多回读的行数（倒序前的尾部窗口）。
     */
    public const MAX_LINES = 5000;

    /**
     * 日志目录。
     */
    protected string $dir;

    public function __construct(?string $dir = null)
    {
        if ($dir !== null && $dir !== '') {
            $this->dir = rtrim($dir, '/\\');
            return;
        }

        $configured = (string)config('log.path', '');

        $this->dir = $configured !== ''
            ? rtrim(dirname($configured), '/\\')
            : var_path('logs');
    }

    /**
     * 日志目录。
     */
    public function directory(): string
    {
        return $this->dir;
    }

    /**
     * 日志文件列表（按修改时间倒序）。
     *
     * @return array<int, array{name: string, size: int, mtime: int}>
     */
    public function files(): array
    {
        if (!is_dir($this->dir)) {
            return [];
        }

        $files = [];

        foreach (glob($this->dir . '/*.log') ?: [] as $file) {
            $files[] = [
                'name'  => basename($file),
                'size'  => (int)@filesize($file),
                'mtime' => (int)@filemtime($file),
            ];
        }

        usort($files, static fn(array $a, array $b) => $b['mtime'] <=> $a['mtime']);

        return $files;
    }

    /**
     * 把请求的文件名解析为绝对路径（只允许当前目录下的 *.log）。
     */
    public function resolve(string $name): ?string
    {
        $name = basename(trim($name));

        if ($name === '' || !preg_match('/^[\w.\-]+\.log$/', $name)) {
            return null;
        }

        $file = $this->dir . '/' . $name;

        return is_file($file) ? $file : null;
    }

    /**
     * 读取日志（最新在前）。
     *
     * @param string $name    文件名
     * @param array  $filter  ['level' => 'ERROR', 'keyword' => 'xxx']
     * @param int    $limit   每页条数
     * @param int    $offset  偏移
     *
     * @return array{total:int, limit:int, offset:int, entries:array}
     */
    public function read(string $name, array $filter = [], int $limit = 100, int $offset = 0): array
    {
        $file = $this->resolve($name);

        if ($file === null) {
            throw new \InvalidArgumentException('日志文件不存在');
        }

        $entries = [];

        foreach ($this->tail($file, self::MAX_LINES) as $line) {
            $entry = $this->parseLine($line);

            if (!$this->match($entry, $filter)) {
                continue;
            }

            $entries[] = $entry;
        }

        // 最新的在上面
        $entries = array_reverse($entries);
        $total   = count($entries);

        return [
            'total'   => $total,
            'limit'   => $limit,
            'offset'  => $offset,
            'entries' => array_slice($entries, $offset, $limit),
        ];
    }

    /**
     * 清空日志文件。
     *
     * @param string $name 空字符串表示清空全部
     *
     * @return int 被清空的文件数
     */
    public function clear(string $name = ''): int
    {
        $targets = [];

        if ($name !== '') {
            $file = $this->resolve($name);
            if ($file === null) {
                throw new \InvalidArgumentException('日志文件不存在');
            }
            $targets[] = $file;
        } else {
            foreach (glob($this->dir . '/*.log') ?: [] as $file) {
                $targets[] = $file;
            }
        }

        $count = 0;

        foreach ($targets as $file) {
            if (@file_put_contents($file, '', LOCK_EX) !== false) {
                $count++;
            }
        }

        return $count;
    }

    /**
     * 读取文件尾部若干行。
     *
     * @return string[]
     */
    protected function tail(string $file, int $maxLines): array
    {
        $handle = @fopen($file, 'rb');

        if (!$handle) {
            return [];
        }

        $chunk  = 8192;
        $buffer = '';

        fseek($handle, 0, SEEK_END);
        $pos = (int)ftell($handle);

        while ($pos > 0 && substr_count($buffer, "\n") <= $maxLines) {
            $read = min($chunk, $pos);
            $pos -= $read;

            fseek($handle, $pos);
            $buffer = (string)fread($handle, $read) . $buffer;
        }

        fclose($handle);

        $lines = preg_split("/\r\n|\n|\r/", $buffer) ?: [];
        $lines = array_values(array_filter($lines, static fn($line) => trim($line) !== ''));

        return array_slice($lines, -$maxLines);
    }

    /**
     * 解析单行日志。
     */
    protected function parseLine(string $line): array
    {
        $entry = [
            'time'    => '',
            'level'   => '',
            'message' => $line,
            'file'    => '',
            'raw'     => $line,
        ];

        if (!preg_match('/^\[([^\]]+)\]\s+(?:[\w.]+\.)?([A-Z]+):\s?(.*)$/s', $line, $match)) {
            return $entry;
        }

        $entry['time']  = $match[1];
        $entry['level'] = $match[2];
        $message        = $match[3];

        // 去掉末尾的 JSON 上下文（含 file / line / trace）
        if (preg_match('/^(.*?)\s+(\{.*\})\s*(\[\])?$/s', $message, $parts)) {
            $entry['message'] = trim($parts[1]);

            $context = json_decode($parts[2], true);
            if (is_array($context) && isset($context['file'])) {
                $entry['file'] = basename((string)$context['file'])
                    . (isset($context['line']) ? ':' . $context['line'] : '');
            }
        } else {
            $entry['message'] = $message;
        }

        return $entry;
    }

    /**
     * 级别 / 关键字过滤。
     */
    protected function match(array $entry, array $filter): bool
    {
        $level = strtoupper(trim((string)($filter['level'] ?? '')));

        if ($level !== '' && $level !== 'ALL' && strtoupper($entry['level']) !== $level) {
            return false;
        }

        $keyword = trim((string)($filter['keyword'] ?? ''));

        if ($keyword !== '' && mb_stripos($entry['raw'], $keyword) === false) {
            return false;
        }

        return true;
    }
}
