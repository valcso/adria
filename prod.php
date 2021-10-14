<?php
namespace App\Helpers;

use App\Models\ProductFiles;
use App\Models\Products;
use App\Models\ProductsOther;
use App\Objects\Product;
use App\Objects\ProductOther;
use Intervention\Image\Image;


class ImporterOtherHelper
{

    //Allowed tables for import/export
    //public $allowedTables = ['products', 'extrafields', 'product_category_glue', 'product_seo'];
    public $allowedTables = ['products_other'];


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
     * @return mixed
     */
    public function importProductsEdit($request)
    {
        $return;
        $importData = [];
        $path = $this->getPath($request);

        \Excel::load($path, function ($reader) use (&$importData) {

            $items = $reader->get();
            foreach ($items AS $item) {
                $pcode = $item['products_other#product_code']; // Product code
               
                if ($pcode == '') {
                    continue;
                }

                foreach ($item AS $key => $value) {
                    $segments = explode('#', $key);
                    $table = $segments[0];
                    $field = @$segments[1];

                    if (in_array($table, $this->allowedTables)) {
                        $importData[$pcode][$table][$field] = str_replace('.0', '', $value);
                    }
                }
            }
        });

        /* checking for new products */
        $importCais =  array_keys($importData);
        $products = ProductsOther::select('product_code')->whereIn('product_code', $importCais)->get();
        $productsCais = $products->pluck('product_code')->toArray();
        $result['diff'] = array_diff($importCais, $productsCais);

        foreach ($importData AS $key => $data) {
            $product = new ProductOther(true, ['product_code' => $key], false);
            $return = $key;
            /* create slug */
//            if (isset($data['products_other']['product_name']) ) {
//                $data['products_other']['slug'] = '';
//            }

            foreach ($data AS $key => $items) {
                /* key is a table name */
                $key_method = '_' . $key;
                $product->$key_method($data[$key]);
            }

            $getProduct= Product::select('name','ef_proizvodjac','ef_dezen')->where('product_code',$return)->first();
            $descName = $getProduct->name;
            $proizvodjac = $getProduct->ef_proizvodjac;
            $dezen = $getProduct -> ef_dezen;
    
            app('App\Http\Controllers\Admin\ProductsController')->updateProductDescription($return,$descName);
            app('App\Http\Controllers\Admin\ProductsController')->updateProductShortDesc($return,$proizvodjac,$dezen); 

            unset($product);
        }

        return $return;
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
                $pcode = $item['products_other#product_code']; // Product code
                if ($pcode == '') {
                    continue;
                }

                foreach ($item AS $key => $value) {
                    $segments = explode('#', $key);
                    $table = $segments[0];
                    $field = @$segments[1];

                    if($key == "products_other#ef_kategorija"){
                        $value = strtolower($value);
                    }

                    if($key == "products_other#product_group"){
                        $value = strtolower($value);
                    }

                    if (in_array($table, $this->allowedTables)) {
                        $importData[$pcode][$table][$field] = str_replace('.0', '', $value);
                    }

                    if($key == "products_other#image" && !is_null($value)){
                        //$this->addImages($pcode, $value);
                    }
                }
            }
        });

        $products = ProductsOther::select('product_code')->whereIn('product_code', array_keys($importData))->get();

        if(!$products->isEmpty())
        {
            return ['all' => array_keys($importData), 'duplicated' => $products->toArray()];
        }


        foreach ($importData AS $key => $data) {
            $return = $key;
            $product = new ProductOther(true, ['product_code' => $key], true);
            /* create slug */
            if (isset($data['products_other']['product_name'])) {
                $data['products_other']['slug'] = '';
            }

            foreach ($data AS $key => $items) {
                /* key is a table name */
                $key_method = '_' . $key;
                // Product object update database
                $product->$key_method($data[$key]);
            }

        $getProduct= Product::select('name','ef_proizvodjac','ef_dezen')->where('product_code',$return)->first();
        $descName = $getProduct->name;
        $proizvodjac = $getProduct->ef_proizvodjac;
        $dezen = $getProduct -> ef_dezen;

        app('App\Http\Controllers\Admin\ProductsController')->updateProductDescription($return,$descName);
        app('App\Http\Controllers\Admin\ProductsController')->updateProductShortDesc($return,$proizvodjac,$dezen);

        unset($product);
        
    }
        return $return;
    }

    public function addImages($cai, $links, $onlyNew = false){
//        $path = 'https://i.stack.imgur.com/koFpQ.png';
////        $filename = basename($path);
//        $filename = $cai.'_'.rand(1,9999).time();
//        Image::make($path)->save(public_path('images/' . $filename));

        $path = public_path("products/" . $cai . '/');
        if (!\File::exists($path)) {
            \File::makeDirectory($path, 0775);
        } else {
            if ($onlyNew) {
                return 0;
            }
        }

        $path_thumbs = public_path("products/" . $cai . '/thumbs/');
        if (!\File::exists($path_thumbs)) {
            \File::makeDirectory($path_thumbs, 0775);
        }

        $allLinks = explode(',', $links);

        if(count($links) > 4) {
            $links = array_slice($allLinks, 0, 4);
        } else {
            $links = $allLinks;
        }


        foreach ($links AS $link){
            //$image = \Image::make($link);
            if(strlen($link) < 20) { continue; }
            $link = str_replace(' ', '', str_replace(',', '',$link) );
            //$extension =  'jpg'; //$image->getClientOriginalExtension();


            //$filename = $cai . '_' . rand(1, 99999) . time() . '.' . $extension;
            $buff = explode('/', $link);
            $filename = end($buff);

            $testFile = @file_get_contents($link);

                if($testFile) {
                    \Image::make($link)->save($path . $filename);
                    $image = \Image::make($path . $filename);

                    $entry = new ProductFiles();
                    //$entry->mime = $image->getClientMimeType();
                    //$entry->original_filename = $image->getClientOriginalName();
                    $entry->filename = $filename;
                    $entry->product_code = $cai;
                    $entry->save();

                    \Image::make($path . $filename)->resize(250, null, function ($constraint) {
                        $constraint->aspectRatio();
                    })->save($path_thumbs . '' . $filename);

                    unset($entry);
                }
            }
            return 1;
    }
}
