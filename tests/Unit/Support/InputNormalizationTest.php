<?php

declare(strict_types=1);

namespace Tests\Unit\Support;

use App\Http\Requests\Sales\StoreCustomerRequest;
use App\Http\Requests\Sales\StoreSaleRequest;
use Illuminate\Routing\Route;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

class InputNormalizationTest extends TestCase
{
    public function test_customer_request_normalizes_devanagari_digits_and_phone(): void
    {
        $request = new StoreCustomerRequest;
        $request->setContainer(app());

        // Raw input with Devanagari numerals and +977 prefix
        $request->merge([
            'name' => 'राम शर्मा',
            'phone' => '+९७७-९८४१२३४५६७',
            'credit_limit' => '५००००',
        ]);

        // Trigger prepareForValidation by validating or calling directly
        $method = new \ReflectionMethod($request, 'prepareForValidation');
        $method->setAccessible(true);
        $method->invoke($request);

        $this->assertEquals('9841234567', $request->input('phone'));
        $this->assertEquals('50000', $request->input('credit_limit'));

        $validator = Validator::make($request->all(), $request->rules());
        $this->assertTrue($validator->passes());
    }

    public function test_sale_request_normalizes_nested_items_devanagari_digits(): void
    {
        $request = new StoreSaleRequest;
        $request->setContainer(app());

        $request->merge([
            'payment_amount' => '१५००.५०',
            'items' => [
                [
                    'product_id' => 1,
                    'quantity' => '५.२५',
                    'unit_price' => '३००.००',
                ],
            ],
        ]);

        $method = new \ReflectionMethod($request, 'prepareForValidation');
        $method->setAccessible(true);
        $method->invoke($request);

        $this->assertEquals('1500.50', $request->input('payment_amount'));
        $this->assertEquals('5.25', $request->input('items.0.quantity'));
        $this->assertEquals('300.00', $request->input('items.0.unit_price'));
    }
}

