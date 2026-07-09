<?php

declare(strict_types=1);

namespace Tests\Unit\Models;

use App\Models\Category;
use App\Models\LabelBatch;
use App\Models\Product;
use App\Models\ProductModel;
use App\Models\TechnicalComposition;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductDeletionTest extends TestCase
{
    use RefreshDatabase;

    private function makeProduct(): Product
    {
        $category = Category::create([
            'name' => 'Cat',
            'code' => 'C-' . substr(uniqid(), -6),
        ]);

        $model = ProductModel::create([
            'category_id'    => $category->id,
            'name'           => 'Model',
            'code'           => 'M-' . substr(uniqid(), -6),
            'type'           => 'Colchón',
            'class'          => 'A',
            'warranty_years' => 1,
            'active'         => true,
        ]);

        return Product::create([
            'product_model_id'  => $model->id,
            'name'              => 'Prod',
            'product_code'      => 'P-' . substr(uniqid(), -6),
            'barcode'           => 'B-' . substr(uniqid(), -6),
            'measurements_text' => '100x200x20',
            'active'            => true,
        ]);
    }

    /** @test */
    public function it_deletes_the_technical_composition_when_the_product_is_deleted(): void
    {
        $product = $this->makeProduct();
        $product->technicalComposition()->create([
            'commercial_name' => 'Demo',
            'manufacturer'    => 'Paraíso',
        ]);

        $compositionId = $product->technicalComposition->id;

        $product->delete();

        $this->assertDatabaseMissing('products', ['id' => $product->id]);
        $this->assertDatabaseMissing('technical_compositions', ['id' => $compositionId]);
    }

    /** @test */
    public function it_blocks_deletion_when_the_product_has_label_batches(): void
    {
        $product = $this->makeProduct();

        $this->makeBatch($product);

        $this->expectException(\RuntimeException::class);

        try {
            $product->delete();
        } finally {
            // El producto debe seguir existiendo tras el intento fallido
            $this->assertDatabaseHas('products', ['id' => $product->id]);
        }
    }

    /** @test */
    public function it_deletes_a_batch_together_with_its_labels_and_warranties(): void
    {
        $product = $this->makeProduct();
        $batch   = $this->makeBatch($product);

        $label = \App\Models\Label::create([
            'label_batch_id'  => $batch->id,
            'product_id'      => $product->id,
            'serial'          => 'SN-DEL-1',
            'sequence_number' => 1,
            'barcode'         => '7861191234260',
            'qr_url'          => 'http://x/p/SN-DEL-1',
            'status'          => 'generated',
        ]);

        \App\Models\Warranty::factory()->create([
            'label_id' => $label->id,
        ]);

        $batch->delete();

        $this->assertDatabaseMissing('label_batches', ['id' => $batch->id]);
        $this->assertDatabaseMissing('labels', ['id' => $label->id]);
        $this->assertDatabaseMissing('warranties', ['label_id' => $label->id]);
    }

    private function makeBatch(Product $product): LabelBatch
    {
        return LabelBatch::create([
            'product_id'            => $product->id,
            'internal_batch_code'   => 'LB-' . substr(uniqid(), -6),
            'customer_batch_number' => 'LOTE-' . substr(uniqid(), -4),
            'customer_batch_date'   => now(),
            'quantity'              => 1,
            'generated_by_user_id'  => User::factory()->create()->id,
            'status'                => 'generated',
        ]);
    }
}
