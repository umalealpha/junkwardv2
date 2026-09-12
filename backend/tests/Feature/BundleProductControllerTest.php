<?php

namespace Tests\Feature;

use Tests\TestCase;
use AlphaDirect\Product;
use AlphaDirect\Productplan;
use AlphaDirect\ProductType;
use Illuminate\Foundation\Testing\RefreshDatabase;

class BundleProductControllerTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Test getting all bundle products
     *
     * @return void
     */
    public function test_get_products()
    {
        // Create test data
        $productType = ProductType::create([
            'name' => 'Health',
            'status' => 1
        ]);

        $product = Product::create([
            'name' => 'Health Insurance',
            'code' => 'HEALTH',
            'description' => 'Comprehensive health coverage',
            'product_type_id' => $productType->id,
            'status' => 1,
            'has_vehicle' => 0,
            'has_member' => 1,
            'min_age' => 18,
            'max_age' => 65
        ]);

        $plan = Productplan::create([
            'product_id' => $product->id,
            'name' => 'Basic Health Plan',
            'code' => 'HEALTH_BASIC',
            'description' => 'Essential health coverage',
            'premium' => 150.00,
            'currency' => 'BWP',
            'billing_frequency' => 'monthly',
            'coverage_amount' => 50000.00,
            'deductible' => 1000.00,
            'status' => 1
        ]);

        // Make API request
        $response = $this->get('/api/bundled-products/getProducts');

        // Assert response
        $response->assertStatus(200)
                ->assertJson([
                    'success' => true,
                    'message' => 'Products retrieved successfully'
                ])
                ->assertJsonStructure([
                    'success',
                    'message',
                    'data' => [
                        'products' => [
                            '*' => [
                                'product_id',
                                'product_name',
                                'product_code',
                                'product_description',
                                'product_type',
                                'is_active',
                                'has_vehicle',
                                'has_member',
                                'min_age',
                                'max_age',
                                'plans'
                            ]
                        ],
                        'metadata' => [
                            'total_products',
                            'total_plans',
                            'currency',
                            'billing_frequencies',
                            'last_updated'
                        ]
                    ]
                ]);
    }

    /**
     * Test getting a specific product by ID
     *
     * @return void
     */
    public function test_get_product_by_id()
    {
        // Create test data
        $productType = ProductType::create([
            'name' => 'Health',
            'status' => 1
        ]);

        $product = Product::create([
            'name' => 'Health Insurance',
            'code' => 'HEALTH',
            'description' => 'Comprehensive health coverage',
            'product_type_id' => $productType->id,
            'status' => 1,
            'has_vehicle' => 0,
            'has_member' => 1,
            'min_age' => 18,
            'max_age' => 65
        ]);

        // Make API request
        $response = $this->get("/api/bundle-products/{$product->id}");

        // Assert response
        $response->assertStatus(200)
                ->assertJson([
                    'success' => true,
                    'message' => 'Product retrieved successfully'
                ]);
    }

    /**
     * Test getting products by type
     *
     * @return void
     */
    public function test_get_products_by_type()
    {
        // Create test data
        $productType = ProductType::create([
            'name' => 'Health',
            'status' => 1
        ]);

        $product = Product::create([
            'name' => 'Health Insurance',
            'code' => 'HEALTH',
            'description' => 'Comprehensive health coverage',
            'product_type_id' => $productType->id,
            'status' => 1,
            'has_vehicle' => 0,
            'has_member' => 1,
            'min_age' => 18,
            'max_age' => 65
        ]);

        // Make API request
        $response = $this->get('/api/bundle-products/type/Health');

        // Assert response
        $response->assertStatus(200)
                ->assertJson([
                    'success' => true,
                    'message' => 'Products retrieved successfully'
                ]);
    }

    /**
     * Test getting non-existent product
     *
     * @return void
     */
    public function test_get_nonexistent_product()
    {
        $response = $this->get('/api/bundle-products/999');

        $response->assertStatus(404)
                ->assertJson([
                    'success' => false,
                    'message' => 'Product not found'
                ]);
    }
}
