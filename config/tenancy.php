<?php

declare(strict_types=1);
use App\Models\Shop;
use App\Scopes\ShopScope;

/**
 * Dukaan OS — Tenancy Configuration
 *
 * This project uses a SHARED DATABASE, SHARED TABLES multi-tenancy model.
 * Every tenant-owned table carries a `shop_id` column that is always populated
 * from TenantContext — never from request input.
 *
 * See Rules/tenancy-rules.md for invariants that must never be violated.
 */

return [

    /*
    |--------------------------------------------------------------------------
    | Tenant Model
    |--------------------------------------------------------------------------
    | The Eloquent model that represents a "shop" (tenant).
    | Populated in Step 01 when the shops table is created.
    */
    'tenant_model' => Shop::class,   // created in Step 01

    /*
    |--------------------------------------------------------------------------
    | Context Resolution
    |--------------------------------------------------------------------------
    | strategy: how TenantContext resolves the current shop.
    |   'auth'        – resolved from the authenticated user's current_shop_id
    |   'subdomain'   – resolved from request subdomain (future option)
    |   'header'      – X-Shop-Id header (API / future option)
    |
    | Deferred open decision: subdomain vs path-prefix — see Docs/06.
    */
    'strategy' => env('TENANCY_STRATEGY', 'auth'),

    /*
    |--------------------------------------------------------------------------
    | Route Middleware Stack
    |--------------------------------------------------------------------------
    | Applied (in order) to every tenant-scoped web route group.
    | 'tenant.resolve'  – resolves & binds TenantContext from session/user
    | 'shop.access'     – verifies user is an active member of that shop
    */
    'middleware' => [
        'tenant.resolve',   // registered in Step 01
        'shop.access',      // registered in Step 01
    ],

    /*
    |--------------------------------------------------------------------------
    | Global Scopes
    |--------------------------------------------------------------------------
    | BelongsToShop trait automatically applies a global scope that filters
    | all queries to the current TenantContext shop_id.
    | Disable per-query with: Model::withoutGlobalScope(ShopScope::class)
    | Only SaaS Admin routes may do this (and must audit-log it).
    */
    'scope_class' => ShopScope::class,   // created in Step 01

    /*
    |--------------------------------------------------------------------------
    | SaaS Admin Guard
    |--------------------------------------------------------------------------
    | Cross-tenant read access for the platform admin.
    | access_level: 'read' — no destructive operations allowed via admin guard.
    | Every cross-tenant access is written to audit_logs.
    */
    'saas_admin' => [
        'guard' => 'saas_admin',
        'access_level' => 'read',
        'audit' => true,
    ],

    /*
    |--------------------------------------------------------------------------
    | Isolation Failures
    |--------------------------------------------------------------------------
    | What to do when tenant context is missing or invalid.
    | 'abort_403'   – throw a 403 response
    | 'redirect'    – redirect to shop picker
    */
    'on_missing_context' => env('TENANCY_ON_MISSING', 'abort_403'),

    /*
    |--------------------------------------------------------------------------
    | Cache
    |--------------------------------------------------------------------------
    | Tenant-specific cache key prefix. Prevents cross-tenant cache collisions.
    | Pattern: "tenant:{shop_id}:{key}"
    */
    'cache_prefix' => 'tenant',

];
