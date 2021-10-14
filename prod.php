<?php
namespace App\Helpers;

use App\Models\Products;
use App\Objects\Product;

class ImporterHelper
{

    //Allowed tables for import/export
    //public $allowedTables = ['products', 'extrafields', 'product_category_glue', 'product_seo'];
    public $allowedTables = ['products', 'extrafields', 'product_seo'];


    /**
     * @param $request
     * @return string
     */
    private function getPath($request)
    {
        $file = $request->file('file');
        $file->move(storage_path() . '/imports/', time() . '_' . $file->getClientOriginalName());
        $path = storage_path() . '/imports/' . time() . '_' . $file->getClientOriginalName();
        return $path;
    }

    /**
     * EDIT products by importer
     * @param $request
     */
    public function importProductsEdit($request)
    {
        $return;
        $importData = [];
        
        $path = $this->getPath($request);

        \Excel::load($path, function ($reader) use (&$importData) {

            $items = $reader->get();
//            dd($items);
            foreach ($items AS $item) {
                // Product code
                $pcode = '';
                if (isset($item['products#product_code'])) $pcode = $item['products#product_code']; 
                if (isset($item['product_seo#product_code'])) $pcode = $item['product_seo#product_code'];
                if (isset($item['product_code'])) $pcode = $item['product_code'];
                if ($pcode == '') {
                    continue;
                }

                foreach ($item AS $key => $value) {
                    if(strpos($key, '#') !== false) {
                        $segments = explode('#', $key);
                        $table = $segments[0];
                        $field = @$segments[1];
                    }
                    else {
                        $table = 'products';
                        $field = $key;
                    }
                    if (in_array($table, $this->allowedTables)) {
                        $importData[$pcode][$table][$field] = str_replace('.0', '', $value);
                    }
                }
            }
        });

        /* checking for new products */
        $importCais =  array_keys($importData);
        $products = Products::select('product_code')->whereIn('product_code', $importCais)->get();
        $productsCais = $products->pluck('product_code')->toArray();
        $result['diff'] = array_diff($importCais, $productsCais);

       
        foreach ($importData AS $key => $data) {
            $return = $key;
            $product = new Product(true, ['product_code' => $key], false);
            /* create slug */
            if (isset($data['products']['name'])) {
                $data['products']['slug'] = '';
            }

            foreach ($data AS $key => $items) {
                /* key is a table name */
                $key_method = '_' . $key;
                $product->$key_method($data[$key]);
                $table = $key;
            }

                $getProduct= Product::select('*')->where('product_code',$return)->first();
                if(!empty($getProduct -> name) && !empty($getProduct -> ef_proizvodjac) && !empty($getProduct -> ef_dezen))
                {
                    $descName = $getProduct->name;
                    $proizvodjac = $getProduct->ef_proizvodjac;
                    $dezen = $getProduct -> ef_dezen;
                    app('App\Http\Controllers\Admin\ProductsController')->updateProductDescription($return,$descName);
                    app('App\Http\Controllers\Admin\ProductsController')->updateProductShortDesc($return,$proizvodjac,$dezen); 
                }
            unset($product);
        }

        return $result;
    }

    /**
     * ADD products by importer
     * @param $request
     * @return array
     */
    public function importProductsAdd($request)
    {
        $return;
        $importData = [];
        $path = $this->getPath($request);

        \Excel::load($path, function ($reader) use (&$importData) {

            $items = $reader->get();
            foreach ($items AS $item) {
                $pcode = '';
                if (isset($item['products#product_code'])) $pcode = $item['products#product_code']; 
                if (isset($item['product_code'])) $pcode = $item['product_code'];
                if ($pcode == '') {
                    continue;
                }
                foreach ($item AS $key => $value) {
                    if(strpos($key, '#') !== false) {
                        $segments = explode('#', $key);
                        $table = $segments[0];
                        $field = @$segments[1];
                    }
                    else {
                        $table = 'products';
                        $field = $key;
                    }
                    if (in_array($table, $this->allowedTables)) {
                        $importData[$pcode][$table][$field] = str_replace('.0', '', $value);
                    }
                }
            }
        });

        $products = Products::select('product_code')->whereIn('product_code', array_keys($importData))->get();

        if(!$products->isEmpty())
        {
            return ['all' => array_keys($importData), 'duplicated' => $products->toArray()];
        }

        foreach ($importData AS $key => $data) {
            $return = $key;
            $product = new Product(true, ['product_code' => $key], true);

            /* create slug */
            if (isset($data['products']['name'])) {
                $data['products']['slug'] = '';
            }

            foreach ($data AS $key => $items) {
                /* key is a table name */
                $key_method = '_' . $key;
                $product->$key_method($data[$key]);
            }

            $getProduct= Product::select('*')->where('product_code',$return)->first();
                if(!empty($getProduct -> name) && !empty($getProduct -> ef_proizvodjac) && !empty($getProduct -> ef_dezen))
                {
                    $descName = $getProduct->name;
                    $proizvodjac = $getProduct->ef_proizvodjac;
                    $dezen = $getProduct -> ef_dezen;
            
                    app('App\Http\Controllers\Admin\ProductsController')->updateProductDescription($return,$descName);
                    app('App\Http\Controllers\Admin\ProductsController')->updateProductShortDesc($return,$proizvodjac,$dezen); 
                }

            unset($product);
        }
    }
}
