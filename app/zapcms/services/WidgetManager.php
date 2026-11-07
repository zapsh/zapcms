<?php

namespace zapcms\services;

use zap\DB;
use zap\traits\SingletonTrait;
use zapcms\Hook;
use zapcms\widgets\CategoriesWidget;
use zapcms\widgets\MenuWidget;
use zapcms\widgets\RecentPostsWidget;
use zapcms\widgets\TextWidget;
use zapcms\widgets\Widget;

/**
 * 小工具管理器
 *
 *  - 注册表：Widget 子类 → key，支持 Hook::filter('widgets.register') 动态扩展；
 *  - 区域（area）：由主题 theme.json 的 widget_areas 声明，默认 sidebar/footer；
 *  - CRUD + 渲染：按区域加载小工具实例，逐个实例化并 output()。
 */
class WidgetManager
{
    use SingletonTrait;

    const TABLE = 'widgets';

    /** @var array<string, class-string<Widget>> */
    protected array $classes = [];

    protected function init(): void
    {
        $this->classes = [
            TextWidget::key()        => TextWidget::class,
            RecentPostsWidget::key() => RecentPostsWidget::class,
            CategoriesWidget::key()  => CategoriesWidget::class,
            MenuWidget::key()        => MenuWidget::class,
        ];
    }

    /**
     * 注册一个小工具类（插件/主题扩展入口）
     */
    public function register(string $class): void
    {
        if (is_subclass_of($class, Widget::class)) {
            $this->classes[$class::key()] = $class;
        }
    }

    /**
     * 注册表（key => class），经 widgets.register 过滤器
     *
     * @return array<string, class-string<Widget>>
     */
    public function classes(): array
    {
        return Hook::applyFilters('widgets.register', $this->classes);
    }

    /**
     * 所有已注册小工具的元信息（供后台列表/表单使用）
     */
    public function registered(): array
    {
        $result = [];
        foreach ($this->classes() as $key => $class) {
            $result[$key] = [
                'key'         => $key,
                'title'       => $class::title(),
                'description' => $class::description(),
                'icon'        => $class::icon(),
                'defaults'    => $class::defaults(),
                'fields'      => $class::fields(),
            ];
        }
        return $result;
    }

    public function getClass(string $key): ?string
    {
        return $this->classes()[$key] ?? null;
    }

    /**
     * 可用区域 [key => title]（来自主题 theme.json，经 widgets.areas 过滤器）
     */
    public function areas(): array
    {
        return Hook::applyFilters('widgets.areas', Theme::instance()->getWidgetAreas());
    }

    public function areaTitle(string $key): string
    {
        return $this->areas()[$key] ?? $key;
    }

    public function all(?string $area = null): array
    {
        $q = DB::table(self::TABLE)->orderBy('sort_order ASC, id ASC');
        if ($area !== null) {
            $q->where('area', $area);
        }
        return $q->get(FETCH_ASSOC) ?: [];
    }

    public function get(int $id): ?array
    {
        $row = DB::table(self::TABLE)->where('id', $id)->fetch(FETCH_ASSOC);
        return $row ?: null;
    }

    public function add(array $data): int
    {
        $now = time();
        $data['config']     = $data['config'] ?? [];
        $data['created_at'] = $now;
        $data['updated_at'] = $now;
        return (int) DB::insert(self::TABLE, $data);
    }

    public function update(array $data, int $id): bool
    {
        $data['updated_at'] = time();
        return DB::update(self::TABLE, $data, ['id' => $id]) !== false;
    }

    public function remove(int $id): bool
    {
        return DB::table(self::TABLE)->where('id', $id)->delete() !== false;
    }

    /**
     * 按给定顺序重排某区域的小工具
     */
    public function reorder(string $area, array $ids): void
    {
        $order = 0;
        foreach ($ids as $id) {
            $order++;
            DB::update(self::TABLE, ['sort_order' => $order], ['id' => (int) $id, 'area' => $area]);
        }
    }

    /**
     * 渲染某区域的所有启用小工具（经 widgets.output_area 过滤器）
     */
    public function renderArea(string $area): string
    {
        $rows = $this->all($area);
        $html = '';
        foreach ($rows as $row) {
            if ((int) ($row['status'] ?? 1) !== 1) {
                continue;
            }
            $class = $this->getClass((string) $row['widget_key']);
            if ($class === null) {
                continue;
            }
            /** @var Widget $widget */
            $widget = new $class($row);
            $html  .= $widget->output();
        }
        return Hook::applyFilters('widgets.output_area', $html, $area);
    }
}
