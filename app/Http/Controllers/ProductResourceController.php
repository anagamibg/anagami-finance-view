<?php

namespace App\Http\Controllers;

use App\Helper\Files;
use App\Helper\Reply;
use App\Models\Product;
use App\Models\ProductResource;

class ProductResourceController extends AccountBaseController
{
    // AFV (upgrade 6.0.19): same module check as ProductController (upstream has none here)
    public function __construct()
    {
        parent::__construct();
        $this->middleware(function ($request, $next) {
            abort_403(!in_array('products', $this->user->modules));

            return $next($request);
        });
    }

    public function destroy($id)
    {
        $resource = ProductResource::findOrFail($id);
        $product = Product::findOrFail($resource->product_id);

        $editPermission = user()->permission('edit_product');
        abort_403(!($editPermission == 'all' || ($editPermission == 'added' && $product->added_by == user()->id)));

        if ($resource->source === ProductResource::SOURCE_FILE && $resource->hashname) {
            Files::deleteFile($resource->hashname, ProductResource::FILE_PATH);
        }

        $resource->delete();

        return Reply::success(__('messages.deleteSuccess'));
    }
}
