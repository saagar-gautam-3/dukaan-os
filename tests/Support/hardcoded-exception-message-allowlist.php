<?php

declare(strict_types=1);

// Internal invariant failures. Controllers do not expose these messages: unexpected
// throwables are reported and rendered with a generic translated response.
return [
    'app/Traits/BelongsToShop.php' => [
        'Cannot create a tenant-owned record without an active TenantContext.' => 'Internal model guard; controller fallback hides unexpected exception details.',
    ],
    'app/Tenancy/TenantContext.php' => [
        'Tenant context is not set.' => 'Internal context precondition; user-facing tenant middleware handles missing context.',
    ],
    'app/Support/Money.php' => [
        'Cannot divide a decimal value by zero.' => 'Internal arithmetic invariant; it is not shown by user-facing controllers.',
    ],
];
