<?php

require_once __DIR__.'/../../vendor/autoload.php';

// 1. Get Asda Product Details.
// 2. Check Details Are Correct.
// 3. Insert, And Check If Inserted Correctly.

use Services\Config;
use Services\Database;
use Services\Loggers;
use Supermarkets\Asda\AsdaProducts;

use PHPUnit\Framework\TestCase;

final class ProductTest extends TestCase {

    public $product_item, $parsed_product_item;

    public function setUp(): Void{
        
        $config = new Config();

        $config->set('env', 'dev');
        
        // 'price',
        // 'weight',

        // 'dietary_info',
        // 'allergen_info',
        // 'url',
        // 'storage',
        // 'site_product_id',

        // Promotion

        $database = $this->createStub(Database::class);
        $logger = $this->createStub(Monolog\Logger::class);

        $asda_product = new AsdaProducts($config, $logger, $database, null);

        $product_response = json_decode(file_get_contents(__DIR__ . '/../../' . $config->get('test_files.product')));

        $this->parsed_product_item = $asda_product->product_details(1, true);
        $this->product_item = $product_response->data->uber_item->items[0];
    }

    public function testCorrectName(): Void {
        $name = $this->parsed_product_item->name;
        $real_name = $this->product_item->item->name;
        $this->assertEquals($real_name, $name);
    }

    public function testCorrectDescription(): Void {
        $description = $this->parsed_product_item->name;
        $real_description = $this->product_item->item->name;
        $this->assertEquals($real_description, $description);
    }

    public function testCorrectBrand(): Void {
        $brand = $this->parsed_product_item->brand;
        $real_brand = $this->product_item->item->brand;
        $this->assertEquals($real_brand, $brand);
    }

    public function testCorrectPrice(): Void {
        $price = $this->parsed_product_item->brand;
        $real_price = $this->product_item->item->brand;
        $this->assertEquals($real_price, $price);
    }

    public function testCorrectWeight(): Void {
        $weight = $this->parsed_product_item->weight;
        $real_weight = $this->product_item->item->extended_item_info->weight;
        $this->assertEquals($real_weight, $weight);
    }

    public function testCorrectDietary(): Void {
        $dietary = $this->parsed_product_item->brand;
        $real_dietary = $this->product_item->item->brand;
        $this->assertEquals($real_dietary, $dietary);
    }

    public function testCorrectAllergen(): Void {
        $allergen = $this->parsed_product_item->allergen_info;
        $real_allergen = $this->product_item->item_enrichment->enrichment_info->allergy_info_formatted_web;
        $this->assertEquals($real_allergen, $allergen);
    }

    public function testCorrectStorage(): Void {
        $storage = $this->parsed_product_item->storage;
        $real_storage = $this->product_item->item_enrichment->enrichment_info->storage;
        $this->assertEquals($real_storage, $storage);
    }

    public function testCorrectUrl(): Void {
        $url = $this->parsed_product_item->url;
        $real_url = 'https://groceries.asda.com/product/' . $this->product_item->item->sku_id;
        $this->assertEquals($real_url, $url);
    }

    public function testCorrectSiteId(): Void {
        $site_product_id = $this->parsed_product_item->site_product_id;
        $real_site_product_id = $this->product_item->item->sku_id;
        $this->assertEquals($real_site_product_id, $site_product_id);
    }

    // public function testCorrectPromotion(): Void {
    //     $this->markTestIncomplete('Promotion Testing Required');
    // }

}
?>