<?php

declare(strict_types=1);

namespace App\Tenancy;

use App\Models\Shop;
use App\Models\ShopUser;
use App\Tenancy\Exceptions\MissingTenantContextException;

class TenantContext
{
    protected ?Shop $shop = null;

    protected ?ShopUser $membership = null;

    public function set(Shop $shop, ?ShopUser $membership = null): void
    {
        $this->shop = $shop;
        $this->membership = $membership;
    }

    public function get(): ?Shop
    {
        return $this->shop;
    }

    public function current(): Shop
    {
        if ($this->shop === null) {
            throw new MissingTenantContextException('Tenant context is not set.');
        }

        return $this->shop;
    }

    public function id(): ?int
    {
        return $this->shop?->id !== null ? (int) $this->shop->id : null;
    }

    public function hasTenant(): bool
    {
        return $this->shop !== null;
    }

    public function membership(): ?ShopUser
    {
        return $this->membership;
    }

    public function clear(): void
    {
        $this->shop = null;
        $this->membership = null;
    }

    protected static function instance(): self
    {
        return app(self::class);
    }

    public static function setShop(Shop $shop, ?ShopUser $membership = null): void
    {
        static::instance()->set($shop, $membership);
    }

    public static function getShop(): ?Shop
    {
        return static::instance()->get();
    }

    public static function currentShop(): Shop
    {
        return static::instance()->current();
    }

    public static function shop(): Shop
    {
        return static::instance()->current();
    }

    public static function getShopId(): ?int
    {
        return static::instance()->id();
    }

    public static function shopId(): ?int
    {
        return static::instance()->id();
    }

    public static function has(): bool
    {
        return static::instance()->hasTenant();
    }

    public static function currentMembership(): ?ShopUser
    {
        return static::instance()->membership();
    }

    /**
     * Execute a callback in the context of the given shop, restoring previous context afterwards.
     *
     * @template T
     *
     * @param  callable(Shop): T  $callback
     * @return T
     */
    public function executeAs(Shop $shop, callable $callback, ?ShopUser $membership = null): mixed
    {
        $previousShop = $this->shop;
        $previousMembership = $this->membership;

        $this->set($shop, $membership);

        try {
            return $callback($shop);
        } finally {
            $this->shop = $previousShop;
            $this->membership = $previousMembership;
        }
    }

    /**
     * Static helper for running a callback as a specific shop.
     *
     * @template T
     *
     * @param  callable(Shop): T  $callback
     * @return T
     */
    public static function runAs(Shop $shop, callable $callback, ?ShopUser $membership = null): mixed
    {
        return static::instance()->executeAs($shop, $callback, $membership);
    }

    public static function reset(): void
    {
        static::instance()->clear();
    }
}
