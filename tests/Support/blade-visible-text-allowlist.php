<?php

declare(strict_types=1);

// Exact visible fragments that are domain marks, abbreviations, or format labels.
// Every entry must include a reason; regular UI copy belongs in lang/{en,ne}.
return [
    'resources/views/layouts/partials/sidebar.blade.php' => [
        'Dukaan OS v1.0' => 'Official product brand and release label.',
        'NPR' => 'ISO currency code used in the currency badge.',
    ],
    'resources/views/welcome.blade.php' => [
        'Dukaan OS' => 'Official product brand.',
    ],
    'resources/views/sales/thermal.blade.php' => [
        'Dukaan OS POS' => 'Official product brand and point-of-sale identifier.',
    ],
];
