<?php

namespace App\Http\Controllers\Admin;

use App\Helpers\ImporterHelper;
use App\Helpers\ImporterOtherHelper;
use App\Http\Requests\ImportRequest;
use App\Models\ProductFiles;
use App\Models\ProductsOther;
use Illuminate\Http\Request;

use App\Http\Requests;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\App;
use Szykra\Notifications\Flash;
use App\Objects\Product;

class ImportController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return Response
     */
    public function index()
    {
        $data['content'] = view('admin.pages.import._dashboard');
        return view('admin.pages.import.index', $data);
    }

    
    public function products(Request $request, $action = '')
    {
        $data['content'] = view('admin.pages.import.products._index');
        $data['action'] = $action;
        return view('admin.pages.import.index', $data);
    }

    /**
     * @param ImportRequest|Request $request
     * @return \Illuminate\Http\RedirectResponse|\Illuminate\Routing\Redirector
     */
    public function import(ImportRequest $request)
    {
        set_time_limit(900);
        if($request->get('group') == 'other'){
            $importer = new ImporterOtherHelper();
        } else {
            $importer = new ImporterHelper();
        }

        $action = $request->action;

        switch($request->_section)
        {
            case 'products':
                switch ($action){
                    case "edit":
                        $result = $importer->importProductsEdit($request);
                        break;
                    case "add":
                        $result = $importer->importProductsAdd($request);
                        break;
                }
                break;
        }

  

       Flash::success('Akcija uspešno obavljena.');
       return redirect( url('admin/import/products/'.$action))->with('result', $result);
    }

    public function ProductsOtherImages(){
        $importer = new ImporterOtherHelper();
        $products = ProductsOther::where('image', '!=', '')->get();

        $count = 0;
        foreach ($products AS $product) {
            $result = $importer->addImages($product->product_code, $product->image, true );
            $count += $result;

            if ($result > 0) {
                echo $count;
            }

            if ($count > 10) {
                break;
            }
        }
    }

    public function fixOtherProductsCais()
    {
        dd('Ova metoda se samo jedanput smela izvrsiti !');
        $products = ProductsOther::all();

        $letter = '';
        foreach ($products AS $product)
        {
            switch ($product->product_group)
            {
                case "patosnice":
                    if($product->ef_kategorija == 'gumene patosnice'){
                        $letter = 'g';
                    }
                    if($product->ef_kategorija == 'tepih patosnice'){
                        $letter = 't';
                    }
                    break;
                case "cerade":
                    $letter = 'c';
                    break;
                case "ratkapne":
                    $letter = 'r';
                    break;
            }
            $oldCai = $product->product_code;
            $cai = substr($oldCai, 0, 3) . $letter . substr($oldCai, 3);

            ProductsOther::where('product_code', $oldCai)->update(['product_code' => $cai]);
            ProductFiles::where('product_code', $oldCai)->update(['product_code' => $cai]);
        }
    }

    public function fixOtherProductsName(){
        dd('Ova metoda se samo jedanput smela izvrsiti !');
        $products = ProductsOther::where('product_group', 'patosnice')->get();
        foreach ($products AS $product)
        {
            if(strlen($product->ef_car_year) > 3) {
                $prefix = str_replace(')', '', $product->ef_car_year );
                $prefix = str_replace('(', '', $prefix);
                $name = trim(preg_replace('/\s*\([^)]*\)/', '', $product->product_name)) . ' ('.$prefix.')';
                ProductsOther::where('product_code', $product->product_code)->update(['product_name' => $name ]);
            }


        }
    }

    public function fixOtherProductsSpaces(){
        $products = ProductsOther::all();
        foreach ($products AS $product)
        {
                $model = ltrim($product->ef_car_model, ' ');
                ProductsOther::where('product_code', $product->product_code)->update(['ef_car_model' => $model ]);
        }
    }
}
