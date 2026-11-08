<?php

namespace zapcms\services\storage;

/** 一次写入的结果 */
final class StorageResult
{
    public function __construct(
        public readonly string $key,
        public readonly string $url,
        public readonly string $driver = 'local',
        public readonly int $size = 0,
        public readonly string $mime = '',
        /** both 模式下 OSS 同步失败时为 false（本地已写入成功） */
        public readonly bool $synced = true,
        public readonly string $warning = '',
    ) {
    }

    public function toArray(): array
    {
        return [
            'key'     => $this->key,
            'url'     => $this->url,
            'driver'  => $this->driver,
            'size'    => $this->size,
            'mime'    => $this->mime,
            'synced'  => $this->synced,
            'warning' => $this->warning,
        ];
    }
}
