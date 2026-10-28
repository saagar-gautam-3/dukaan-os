<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\MovementType;
use App\Models\Category;
use App\Models\Product;
use App\Models\Role;
use App\Models\Shop;
use App\Models\ShopUser;
use App\Models\StockBalance;
use App\Models\StockMovement;
use App\Models\Supplier;
use App\Models\Unit;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\RbacService;
use App\Tenancy\TenantContext;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DemoShopSeeder extends Seeder
{
    /**
     * Run the demo shop seeds with realistic Nepali Kirana data.
     * Keep this seeder separate from production seeding.
     */
    public function run(RbacService $rbacService): void
    {
        // 1. Ensure units and permissions exist
        $this->call(UnitSeeder::class);
        $rbacService->syncSystemPermissions();

        $units = Unit::all()->keyBy('symbol');

        // 2. Create Demo Shop
        /** @var Shop $shop */
        $shop = Shop::withoutGlobalScopes()->firstOrCreate(
            ['slug' => 'annapurna-kirana'],
            [
                'name' => 'Annapurna Kirana & General Store',
                'business_type' => 'kirana',
                'timezone' => 'Asia/Kathmandu',
                'settings' => [
                    'currency' => 'NPR',
                    'pan_no' => '301234567',
                    'allow_negative_stock' => false,
                ],
                'is_active' => true,
            ]
        );

        // 3. Assign Default Roles
        $roles = $rbacService->createDefaultRolesForShop($shop);

        // 4. Create Staff in each role
        $staffMembers = [
            [
                'name' => 'Ram Shrestha',
                'email' => 'owner@annapurna.com',
                'phone' => '9841000001',
                'role' => 'Shop Owner',
            ],
            [
                'name' => 'Sita Sharma',
                'email' => 'manager@annapurna.com',
                'phone' => '9841000002',
                'role' => 'Manager',
            ],
            [
                'name' => 'Hari KC',
                'email' => 'cashier@annapurna.com',
                'phone' => '9841000003',
                'role' => 'Cashier',
            ],
            [
                'name' => 'Bikash Gurung',
                'email' => 'inventory@annapurna.com',
                'phone' => '9841000004',
                'role' => 'Inventory Staff',
            ],
            [
                'name' => 'Laxmi Adhikari',
                'email' => 'accountant@annapurna.com',
                'phone' => '9841000005',
                'role' => 'Accountant',
            ],
        ];

        $users = [];
        foreach ($staffMembers as $staff) {
            $user = User::firstOrCreate(
                ['email' => $staff['email']],
                [
                    'name' => $staff['name'],
                    'phone' => $staff['phone'],
                    'password' => Hash::make('password'),
                ]
            );

            /** @var Role $role */
            $role = $roles[$staff['role']];

            ShopUser::firstOrCreate(
                [
                    'shop_id' => $shop->id,
                    'user_id' => $user->id,
                ],
                [
                    'role_id' => $role->id,
                    'status' => 'active',
                ]
            );

            $users[$staff['role']] = $user;
        }

        $owner = $users['Shop Owner'];

        // 5. Use TenantContext::runAs to seed tenant-owned data
        TenantContext::runAs($shop, function () use ($shop, $units, $owner) {
            // Default Warehouse
            /** @var Warehouse $warehouse */
            $warehouse = Warehouse::firstOrCreate(
                ['shop_id' => $shop->id, 'name' => 'Main Godown'],
                [
                    'code' => 'MAIN-01',
                    'address' => 'Ason, Kathmandu',
                    'is_default' => true,
                    'is_active' => true,
                ]
            );

            // Default Supplier
            $supplier = Supplier::firstOrCreate(
                ['shop_id' => $shop->id, 'name' => 'Nepal Consumer Products Suppliers'],
                [
                    'phone' => '9851098765',
                    'address' => 'Kalimati, Kathmandu',
                    'pan_no' => '300987654',
                    'is_active' => true,
                ]
            );

            // Categories
            $categoriesData = [
                'Rice & Grains' => 'चामल र दाल',
                'Edible Oil & Ghee' => 'तेल तथा घ्यू',
                'Spices & Salt' => 'मसाला र नुन',
                'Tea, Coffee & Sugar' => 'चिया, कफी र चिनी',
                'Snacks & Noodles' => 'खाजा र चाउचाउ',
                'Dairy & Cookies' => 'दुग्धजन्य र बिस्कुट',
                'Soaps & Detergents' => 'साबुन र सरफ',
            ];

            $categories = [];
            foreach ($categoriesData as $en => $ne) {
                $categories[$en] = Category::firstOrCreate(
                    ['shop_id' => $shop->id, 'name' => "{$en} ({$ne})"],
                    ['is_active' => true]
                );
            }

            // 30 Realistic Nepali Kirana Products
            $productsData = [
                // Rice & Grains
                [
                    'name' => 'Mansuli Rice 25kg',
                    'name_ne' => 'मन्सुली चामल २५ केजी',
                    'cat' => 'Rice & Grains',
                    'unit' => 'bag',
                    'sku' => 'RC-MAN-25',
                    'barcode' => '8901001000011',
                    'cost' => '1800.00',
                    'sell' => '2100.00',
                    'stock' => '40.000',
                ],
                [
                    'name' => 'Jeera Masino Rice 25kg',
                    'name_ne' => 'जिरा मसिनो चामल २५ केजी',
                    'cat' => 'Rice & Grains',
                    'unit' => 'bag',
                    'sku' => 'RC-JRA-25',
                    'barcode' => '8901001000028',
                    'cost' => '2400.00',
                    'sell' => '2750.00',
                    'stock' => '25.000',
                ],
                [
                    'name' => 'Basmati Rice 20kg',
                    'name_ne' => 'बासमती चामल २० केजी',
                    'cat' => 'Rice & Grains',
                    'unit' => 'bag',
                    'sku' => 'RC-BAS-20',
                    'barcode' => '8901001000035',
                    'cost' => '2800.00',
                    'sell' => '3200.00',
                    'stock' => '15.000',
                ],
                [
                    'name' => 'Taichin Chiura',
                    'name_ne' => 'ताइचिन चिउरा १ केजी',
                    'cat' => 'Rice & Grains',
                    'unit' => 'kg',
                    'sku' => 'GR-CHR-01',
                    'barcode' => '8901001000042',
                    'cost' => '110.00',
                    'sell' => '135.00',
                    'stock' => '60.000',
                ],
                [
                    'name' => 'Mulpani Masoor Dal',
                    'name_ne' => 'मुसुरो दाल १ केजी',
                    'cat' => 'Rice & Grains',
                    'unit' => 'kg',
                    'sku' => 'DL-MSR-01',
                    'barcode' => '8901001000059',
                    'cost' => '145.00',
                    'sell' => '170.00',
                    'stock' => '50.000',
                ],
                [
                    'name' => 'Rahar Dal',
                    'name_ne' => 'रहर दाल १ केजी',
                    'cat' => 'Rice & Grains',
                    'unit' => 'kg',
                    'sku' => 'DL-RHR-01',
                    'barcode' => '8901001000066',
                    'cost' => '195.00',
                    'sell' => '230.00',
                    'stock' => '45.000',
                ],
                [
                    'name' => 'Chana Dal',
                    'name_ne' => 'चना दाल १ केजी',
                    'cat' => 'Rice & Grains',
                    'unit' => 'kg',
                    'sku' => 'DL-CHN-01',
                    'barcode' => '8901001000073',
                    'cost' => '130.00',
                    'sell' => '155.00',
                    'stock' => '35.000',
                ],

                // Edible Oil & Ghee
                [
                    'name' => 'Dhara Mustard Oil 1L',
                    'name_ne' => 'धारा तोरीको तेल १ लिटर',
                    'cat' => 'Edible Oil & Ghee',
                    'unit' => 'pkt',
                    'sku' => 'OL-MSR-01',
                    'barcode' => '8901001000080',
                    'cost' => '245.00',
                    'sell' => '275.00',
                    'stock' => '80.000',
                ],
                [
                    'name' => 'Sunflow Sunflower Oil 1L',
                    'name_ne' => 'सनफ्लो सूर्यमुखी तेल १ लिटर',
                    'cat' => 'Edible Oil & Ghee',
                    'unit' => 'pkt',
                    'sku' => 'OL-SNF-01',
                    'barcode' => '8901001000097',
                    'cost' => '225.00',
                    'sell' => '255.00',
                    'stock' => '60.000',
                ],
                [
                    'name' => 'DDC Pure Ghee 1L',
                    'name_ne' => 'डीडीसी शुद्ध घ्यू १ लिटर',
                    'cat' => 'Edible Oil & Ghee',
                    'unit' => 'pc',
                    'sku' => 'GH-DDC-01',
                    'barcode' => '8901001000103',
                    'cost' => '1150.00',
                    'sell' => '1300.00',
                    'stock' => '20.000',
                ],

                // Spices & Salt
                [
                    'name' => 'Aayodhi Iodized Salt 1kg',
                    'name_ne' => 'आयोडिनयुक्त नुन १ केजी',
                    'cat' => 'Spices & Salt',
                    'unit' => 'pkt',
                    'sku' => 'SL-AYO-01',
                    'barcode' => '8901001000110',
                    'cost' => '22.00',
                    'sell' => '26.00',
                    'stock' => '150.000',
                ],
                [
                    'name' => 'Everest Meat Masala 100g',
                    'name_ne' => 'एभरेस्ट मिट मसला १०० ग्राम',
                    'cat' => 'Spices & Salt',
                    'unit' => 'pkt',
                    'sku' => 'SP-EVR-MT',
                    'barcode' => '8901001000127',
                    'cost' => '85.00',
                    'sell' => '105.00',
                    'stock' => '50.000',
                ],
                [
                    'name' => 'Century Garam Masala 100g',
                    'name_ne' => 'सेन्चुरी गरम मसला १०० ग्राम',
                    'cat' => 'Spices & Salt',
                    'unit' => 'pkt',
                    'sku' => 'SP-CEN-GM',
                    'barcode' => '8901001000134',
                    'cost' => '75.00',
                    'sell' => '95.00',
                    'stock' => '40.000',
                ],
                [
                    'name' => 'Century Turmeric Powder 500g',
                    'name_ne' => 'सेन्चुरी बेसार धुलो ५०० ग्राम',
                    'cat' => 'Spices & Salt',
                    'unit' => 'pkt',
                    'sku' => 'SP-CEN-TR',
                    'barcode' => '8901001000141',
                    'cost' => '120.00',
                    'sell' => '150.00',
                    'stock' => '30.000',
                ],

                // Tea, Coffee & Sugar
                [
                    'name' => 'Tokla Gold Tea 500g',
                    'name_ne' => 'तोक्ला गोल्ड चिया ५०० ग्राम',
                    'cat' => 'Tea, Coffee & Sugar',
                    'unit' => 'pkt',
                    'sku' => 'TE-TOK-500',
                    'barcode' => '8901001000158',
                    'cost' => '320.00',
                    'sell' => '380.00',
                    'stock' => '35.000',
                ],
                [
                    'name' => 'Red Label Tea 500g',
                    'name_ne' => 'रेड लेबल चिया ५०० ग्राम',
                    'cat' => 'Tea, Coffee & Sugar',
                    'unit' => 'pkt',
                    'sku' => 'TE-RDL-500',
                    'barcode' => '8901001000165',
                    'cost' => '340.00',
                    'sell' => '400.00',
                    'stock' => '25.000',
                ],
                [
                    'name' => 'Nescafe Classic Coffee 50g',
                    'name_ne' => 'नेस्क्याफे कफी ५० ग्राम',
                    'cat' => 'Tea, Coffee & Sugar',
                    'unit' => 'pc',
                    'sku' => 'CF-NES-50',
                    'barcode' => '8901001000172',
                    'cost' => '195.00',
                    'sell' => '240.00',
                    'stock' => '30.000',
                ],
                [
                    'name' => 'White Refined Sugar 1kg',
                    'name_ne' => 'सेतो चिनी १ केजी',
                    'cat' => 'Tea, Coffee & Sugar',
                    'unit' => 'kg',
                    'sku' => 'SG-WHT-01',
                    'barcode' => '8901001000189',
                    'cost' => '98.00',
                    'sell' => '115.00',
                    'stock' => '120.000',
                ],

                // Snacks & Noodles
                [
                    'name' => 'Wai Wai Chicken Noodles 75g',
                    'name_ne' => 'वाइवाइ चाउचाउ ७५ ग्राम',
                    'cat' => 'Snacks & Noodles',
                    'unit' => 'pkt',
                    'sku' => 'ND-WAI-CHK',
                    'barcode' => '8901001000196',
                    'cost' => '20.00',
                    'sell' => '25.00',
                    'stock' => '200.000',
                ],
                [
                    'name' => 'Rumpum Noodles 75g',
                    'name_ne' => 'रम्पम चाउचाउ ७५ ग्राम',
                    'cat' => 'Snacks & Noodles',
                    'unit' => 'pkt',
                    'sku' => 'ND-RUM-01',
                    'barcode' => '8901001000202',
                    'cost' => '20.00',
                    'sell' => '25.00',
                    'stock' => '150.000',
                ],
                [
                    'name' => 'Current Extra Hot Noodles 100g',
                    'name_ne' => 'करेन्ट पिरो चाउचाउ १०० ग्राम',
                    'cat' => 'Snacks & Noodles',
                    'unit' => 'pkt',
                    'sku' => 'ND-CUR-HOT',
                    'barcode' => '8901001000219',
                    'cost' => '45.00',
                    'sell' => '55.00',
                    'stock' => '100.000',
                ],
                [
                    'name' => 'Kurmure Snacks 100g',
                    'name_ne' => 'कुर्मुरे खाजा १०० ग्राम',
                    'cat' => 'Snacks & Noodles',
                    'unit' => 'pkt',
                    'sku' => 'SN-KUR-100',
                    'barcode' => '8901001000226',
                    'cost' => '38.00',
                    'sell' => '50.00',
                    'stock' => '80.000',
                ],

                // Dairy & Cookies
                [
                    'name' => 'Parle-G Biscuits 250g',
                    'name_ne' => 'पार्ले-जी बिस्कुट २५० ग्राम',
                    'cat' => 'Dairy & Cookies',
                    'unit' => 'pkt',
                    'sku' => 'BS-PRL-250',
                    'barcode' => '8901001000233',
                    'cost' => '40.00',
                    'sell' => '50.00',
                    'stock' => '90.000',
                ],
                [
                    'name' => 'Good Day Butter Cookies 120g',
                    'name_ne' => 'गुड डे बिस्कुट १२० ग्राम',
                    'cat' => 'Dairy & Cookies',
                    'unit' => 'pkt',
                    'sku' => 'BS-GDY-120',
                    'barcode' => '8901001000240',
                    'cost' => '42.00',
                    'sell' => '50.00',
                    'stock' => '70.000',
                ],
                [
                    'name' => 'Oreo Chocolate Cookies 120g',
                    'name_ne' => 'ओरियो कुकिज १२० ग्राम',
                    'cat' => 'Dairy & Cookies',
                    'unit' => 'pkt',
                    'sku' => 'BS-ORO-120',
                    'barcode' => '8901001000257',
                    'cost' => '50.00',
                    'sell' => '60.00',
                    'stock' => '60.000',
                ],
                [
                    'name' => 'Amul Butter 100g',
                    'name_ne' => 'अमूल बटर १०० ग्राम',
                    'cat' => 'Dairy & Cookies',
                    'unit' => 'pc',
                    'sku' => 'DR-AML-100',
                    'barcode' => '8901001000264',
                    'cost' => '95.00',
                    'sell' => '115.00',
                    'stock' => '40.000',
                ],

                // Soaps & Detergents
                [
                    'name' => 'Lifebuoy Total Soap 125g',
                    'name_ne' => 'लाइफबोय साबुन १२५ ग्राम',
                    'cat' => 'Soaps & Detergents',
                    'unit' => 'pc',
                    'sku' => 'SP-LFB-125',
                    'barcode' => '8901001000271',
                    'cost' => '48.00',
                    'sell' => '58.00',
                    'stock' => '100.000',
                ],
                [
                    'name' => 'Dettol Original Soap 125g',
                    'name_ne' => 'डेटोल साबुन १२५ ग्राम',
                    'cat' => 'Soaps & Detergents',
                    'unit' => 'pc',
                    'sku' => 'SP-DTL-125',
                    'barcode' => '8901001000288',
                    'cost' => '60.00',
                    'sell' => '72.00',
                    'stock' => '80.000',
                ],
                [
                    'name' => 'Surf Excel Quick Wash 1kg',
                    'name_ne' => 'सर्फ एक्सेल १ केजी',
                    'cat' => 'Soaps & Detergents',
                    'unit' => 'pkt',
                    'sku' => 'DT-SRF-01',
                    'barcode' => '8901001000295',
                    'cost' => '230.00',
                    'sell' => '270.00',
                    'stock' => '50.000',
                ],
                [
                    'name' => 'Vim Dishwash Bar 300g',
                    'name_ne' => 'भिम भाँडा माझ्ने साबुन ३०० ग्राम',
                    'cat' => 'Soaps & Detergents',
                    'unit' => 'pc',
                    'sku' => 'DW-VIM-300',
                    'barcode' => '8901001000301',
                    'cost' => '35.00',
                    'sell' => '45.00',
                    'stock' => '90.000',
                ],
            ];

            foreach ($productsData as $data) {
                /** @var Category $category */
                $category = $categories[$data['cat']];
                /** @var Unit $unit */
                $unit = $units[$data['unit']];

                /** @var Product $product */
                $product = Product::firstOrCreate(
                    [
                        'shop_id' => $shop->id,
                        'sku' => $data['sku'],
                    ],
                    [
                        'category_id' => $category->id,
                        'unit_id' => $unit->id,
                        'name' => $data['name'],
                        'name_ne' => $data['name_ne'],
                        'barcode' => $data['barcode'],
                        'cost_price' => $data['cost'],
                        'selling_price' => $data['sell'],
                        'track_stock' => true,
                        'low_stock_threshold' => 10.000,
                        'is_active' => true,
                    ]
                );

                // Initialize stock balance
                $balance = StockBalance::firstOrCreate(
                    [
                        'shop_id' => $shop->id,
                        'warehouse_id' => $warehouse->id,
                        'product_id' => $product->id,
                    ],
                    [
                        'quantity' => $data['stock'],
                    ]
                );

                // Create initial opening stock movement
                StockMovement::firstOrCreate(
                    [
                        'shop_id' => $shop->id,
                        'warehouse_id' => $warehouse->id,
                        'product_id' => $product->id,
                        'movement_type' => MovementType::Opening,
                    ],
                    [
                        'quantity' => $data['stock'],
                        'unit_cost' => $data['cost'],
                        'reference_type' => null,
                        'reference_id' => null,
                        'notes' => 'Opening stock initialization',
                        'created_by' => $owner->id,
                    ]
                );
            }
        });
    }
}
