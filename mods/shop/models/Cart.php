<?php
/**
 * 购物车模型（基于 Session）
 */

namespace mods\shop\models;

class Cart
{
    private static string $sessionKey = 'shop_cart';

    /**
     * 获取购物车全部内容
     */
    public static function all(): array
    {
        return $_SESSION[self::$sessionKey] ?? [];
    }

    /**
     * 添加到购物车
     */
    public static function add(int $productId, int $quantity = 1): void
    {
        $cart = self::all();
        if (isset($cart[$productId])) {
            $cart[$productId]['quantity'] += $quantity;
        } else {
            $product = Product::createQuery()->where('id', $productId)->first();
            if (!$product) return;

            $cart[$productId] = [
                'id'       => $product['id'],
                'title'    => $product['title'],
                'image'    => $product['image'],
                'price'    => (float) $product['price'],
                'quantity' => $quantity,
            ];
        }
        $_SESSION[self::$sessionKey] = $cart;
    }

    /**
     * 更新数量
     */
    public static function update(int $productId, int $quantity): void
    {
        $cart = self::all();
        if ($quantity <= 0) {
            unset($cart[$productId]);
        } elseif (isset($cart[$productId])) {
            $cart[$productId]['quantity'] = $quantity;
        }
        $_SESSION[self::$sessionKey] = $cart;
    }

    /**
     * 移除
     */
    public static function remove(int $productId): void
    {
        $cart = self::all();
        unset($cart[$productId]);
        $_SESSION[self::$sessionKey] = $cart;
    }

    /**
     * 清空
     */
    public static function clear(): void
    {
        unset($_SESSION[self::$sessionKey]);
    }

    /**
     * 总数量
     */
    public static function count(): int
    {
        return array_sum(array_column(self::all(), 'quantity'));
    }

    /**
     * 总金额
     */
    public static function total(): float
    {
        $total = 0;
        foreach (self::all() as $item) {
            $total += $item['price'] * $item['quantity'];
        }
        return $total;
    }
}
