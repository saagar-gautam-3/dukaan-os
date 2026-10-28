<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Unit;
use Illuminate\Database\Seeder;

class UnitSeeder extends Seeder
{
    /**
     * Run the unit seeds.
     */
    public function run(): void
    {
        $units = [
            ['name' => 'Kilogram (किलोग्राम)', 'symbol' => 'kg', 'is_fractional' => true],
            ['name' => 'Gram (ग्राम)', 'symbol' => 'g', 'is_fractional' => true],
            ['name' => 'Litre (लिटर)', 'symbol' => 'l', 'is_fractional' => true],
            ['name' => 'Millilitre (मिलिलिटर)', 'symbol' => 'ml', 'is_fractional' => true],
            ['name' => 'Piece (गोटा/थान)', 'symbol' => 'pc', 'is_fractional' => false],
            ['name' => 'Packet (प्याकेट)', 'symbol' => 'pkt', 'is_fractional' => false],
            ['name' => 'Carton (कार्टुन)', 'symbol' => 'ctn', 'is_fractional' => false],
            ['name' => 'Bora / Bag (बोरा)', 'symbol' => 'bag', 'is_fractional' => true],
            ['name' => 'Dozen (दर्जन)', 'symbol' => 'dz', 'is_fractional' => false],
            ['name' => 'Box (बक्स)', 'symbol' => 'box', 'is_fractional' => false],
        ];

        foreach ($units as $unit) {
            Unit::firstOrCreate(
                ['symbol' => $unit['symbol']],
                [
                    'name' => $unit['name'],
                    'is_fractional' => $unit['is_fractional'],
                ]
            );
        }
    }
}
