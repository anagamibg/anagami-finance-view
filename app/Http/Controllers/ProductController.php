<?php

namespace App\Http\Controllers;

use App\Models\Tax;
use App\Helper\Files;
use App\Helper\Reply;
use App\Helper\Common;
use App\Models\Order;
use App\Models\Product;
use App\Models\UnitType;
use App\Models\OrderCart;
use App\Models\ProductFiles;
use App\Models\ProductResource;
use Illuminate\Http\Request;
use App\Imports\ProductImport;
use App\Jobs\ImportProductJob;
use App\Models\ProductCategory;
use App\Models\ProductSubCategory;
use App\DataTables\ProductsDataTable;
use App\Http\Controllers\AccountBaseController;
use App\Http\Requests\Product\StoreProductRequest;
use App\Http\Requests\Admin\Employee\ImportRequest;
use App\Http\Requests\Product\UpdateProductRequest;
use App\Http\Requests\Admin\Employee\ImportProcessRequest;
use App\Traits\ImportExcel;

class ProductController extends AccountBaseController
{
    use ImportExcel;

    public function __construct()
    {
        parent::__construct();
        $this->pageTitle = 'app.menu.products';
        $this->middleware(
            function ($request, $next) {
                in_array('client', user_roles()) ? abort_403(!(in_array('orders', $this->user->modules) && user()->permission('add_order') == 'all')) : abort_403(!in_array('products', $this->user->modules));

                return $next($request);
            }
        );
    }

    /**
     * @param  ProductsDataTable $dataTable
     * @return mixed|void
     */
    public function index(ProductsDataTable $dataTable)
    {
        $viewPermission = user()->permission('view_product');
        abort_403(!in_array($viewPermission, ['all', 'added']));

        $productDetails = [];
        $productDetails = OrderCart::all();
        $this->productDetails = $productDetails;

        $this->totalProducts = Product::count();
        $this->cartProductCount = OrderCart::where('client_id', user()->id)->sum('quantity');

        $this->categories = ProductCategory::all();
        $this->subCategories = ProductSubCategory::all();
        $this->unitTypes = UnitType::all();

        return $dataTable->render('products.index', $this->data);
    }

    /**
     * Catalog-style grid listing for products index.
     */
    public function grid(Request $request)
    {
        $viewPermission = user()->permission('view_product');
        abort_403(!in_array($viewPermission, ['all', 'added']));

        $products = Product::with('tax', 'category', 'subCategory')
            ->select('id', 'name', 'sku', 'price', 'taxes', 'allow_purchase', 'added_by', 'default_image', 'category_id', 'sub_category_id', 'description');

        if (!is_null($request->category_id) && $request->category_id != 'all' && $request->category_id > 0) {
            $products->where('category_id', $request->category_id);
        }

        if (!is_null($request->unit_type_id) && $request->unit_type_id != 'all') {
            $products->where('unit_id', $request->unit_type_id);
        }

        if (!is_null($request->sub_category_id) && $request->sub_category_id != 'all' && $request->sub_category_id > 0) {
            $products->where('sub_category_id', $request->sub_category_id);
        }

        if ($request->searchText != '') {
            $safeTerm = Common::safeString($request->searchText);
            $products->where(function ($query) use ($safeTerm) {
                $query->where('products.name', 'like', '%' . $safeTerm . '%')
                    ->orWhere('products.sku', 'like', '%' . $safeTerm . '%')
                    ->orWhere('products.price', 'like', '%' . $safeTerm . '%');
            });
        }

        if ($viewPermission == 'added') {
            $products->where('products.added_by', user()->id);
        }

        if (in_array('client', user_roles())) {
            $products->where('products.allow_purchase', 1);
        }

        $this->products = $products->orderBy('products.name')->get();
        $this->editProductPermission = user()->permission('edit_product');
        $this->deleteProductPermission = user()->permission('delete_product');
        $this->cartProductIds = in_array('client', user_roles())
            ? OrderCart::where('client_id', user()->id)->pluck('product_id')->toArray()
            : [];

        $html = view('products.ajax.grid', $this->data)->render();

        return Reply::dataOnly(['status' => 'success', 'html' => $html]);
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {
        $this->pageTitle = __('app.menu.addProducts');

        $this->addPermission = user()->permission('add_product');
        abort_403(!in_array($this->addPermission, ['all', 'added']));
        $this->taxes = Tax::all();
        $this->categories = ProductCategory::all();

        $product = new Product();

        $this->unit_types = UnitType::all();

        $getCustomFieldGroupsWithFields = $product->getCustomFieldGroupsWithFields();

        if ($getCustomFieldGroupsWithFields) {
            $this->fields = $getCustomFieldGroupsWithFields->fields;
        }
        $this->view = 'products.ajax.create';

        if (request()->ajax()) {
            return $this->returnAjax($this->view);
        }

        return view('products.create', $this->data);
    }

    /**
     *
     * @param  StoreProductRequest $request
     * @return void
     */
    public function store(StoreProductRequest $request)
    {
        $this->addPermission = user()->permission('add_product');
        abort_403(!in_array($this->addPermission, ['all', 'added']));

        $this->validateProductResources($request); // AFV (upgrade 6.0.19)

        $product = new Product();
        $product->name = $request->name;
        $product->price = $request->price;
        $product->cost_price = $request->filled('cost_price') ? $request->cost_price : null; // AFV (S8)
        $product->taxes = $request->tax ? json_encode($request->tax) : null;
        $product->description = trim_editor($request->description);
        $product->hsn_sac_code = $request->hsn_sac_code;
        $product->sku = $request->sku;
        $product->unit_id = $request->unit_type;
        $product->allow_purchase = $request->purchase_allow == 'no';
        $product->downloadable = $request->downloadable == 'true';
        $product->category_id = ($request->category_id) ?: null;
        $product->sub_category_id = ($request->sub_category_id) ?: null;

        if (request()->hasFile('downloadable_file') && request()->downloadable == 'true') {
            Files::deleteFile($product->downloadable_file, ProductFiles::FILE_PATH);
            $product->downloadable_file = Files::uploadLocalOrS3(request()->downloadable_file, ProductFiles::FILE_PATH);
        }

        $product->save();

        // To add custom fields data
        if ($request->custom_fields_data) {
            $product->updateCustomFieldData($request->custom_fields_data);
        }

        $this->syncProductResources($product, $request);

        $redirectUrl = urldecode($request->redirect_url);

        if ($redirectUrl == '') {
            $redirectUrl = route('products.index');
        }

        if($request->add_more == 'true') {
            $html = $this->create();

            return Reply::successWithData(__('messages.recordSaved'), ['html' => $html, 'add_more' => true, 'productID' => $product->id, 'defaultImage' => $request->default_image ?? 0]);
        }

        return Reply::successWithData(__('messages.recordSaved'), ['redirectUrl' => $redirectUrl, 'productID' => $product->id, 'defaultImage' => $request->default_image ?? 0]);

    }

    /**
     * Display the specified resource.
     *
     * @param  int $id
     * @return \Illuminate\Http\Response
     */
    public function show($id)
    {
        $this->product = Product::with('category', 'subCategory', 'files', 'unit', 'resources')->findOrFail($id)->withCustomFields();
        $this->viewPermission = user()->permission('view_product');
        abort_403(!($this->viewPermission == 'all' || ($this->viewPermission == 'added' && $this->product->added_by == user()->id)));

        $this->taxes = Tax::withTrashed()->get();
        $this->pageTitle = $this->product->name;

        $this->taxValue = '';
        $taxes = [];

        foreach ($this->taxes as $tax) {
            if ($this->product && isset($this->product->taxes) && array_search($tax->id, json_decode($this->product->taxes)) !== false) {
                $taxes[] = $tax->tax_name . ' : ' . $tax->rate_percent . '%';
            }
        }

        $this->taxValue = implode(', ', $taxes);

        $priceWithTax = $this->product->price;
        if (!is_null($this->product->taxes)) {
            $totalTax = 0;
            foreach (json_decode($this->product->taxes) as $taxId) {
                $prodTax = Product::taxbyid($taxId)->first();
                if ($prodTax) {
                    $totalTax += ($this->product->price * ($prodTax->rate_percent / 100));
                }
            }
            $priceWithTax = $this->product->price + $totalTax;
        }
        $this->priceWithTax = $priceWithTax;

        $getCustomFieldGroupsWithFields = $this->product->getCustomFieldGroupsWithFields();

        if ($getCustomFieldGroupsWithFields) {
            $this->fields = $getCustomFieldGroupsWithFields->fields;
        }

        $this->view = 'products.ajax.show';

        if (request()->ajax()) {
            return $this->returnAjax($this->view);
        }

        return view('products.create', $this->data);

    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  int $id
     * @return \Illuminate\Http\Response
     */
    public function edit($id)
    {
        $this->product = Product::with('resources')->findOrFail($id)->withCustomFields();

        $this->editPermission = user()->permission('edit_product');
        abort_403(!($this->editPermission == 'all' || ($this->editPermission == 'added' && $this->product->added_by == user()->id)));

        $this->taxes = Tax::all();
        $this->categories = ProductCategory::all();
        $this->unit_types = UnitType::all();
        $this->subCategories = !is_null($this->product->sub_category_id) ? ProductSubCategory::where('category_id', $this->product->category_id)->get() : [];
        $this->pageTitle = __('app.menu.editProducts'); // AFV (upgrade 6.0.19): was "Update" + "Products" ("Актуализирай Продукти"), now the form's own heading


        $images = [];

        if (isset($this->product) && isset($this->product->files)) {
            foreach ($this->product->files as $file) {
                $image['id'] = $file->id;
                $image['name'] = $file->filename;
                $image['hashname'] = $file->hashname;
                $image['file_url'] = $file->file_url;
                $image['size'] = $file->size;
                $images[] = $image;
            }
        }

        $this->images = json_encode($images);

        $getCustomFieldGroupsWithFields = $this->product->getCustomFieldGroupsWithFields();

        if ($getCustomFieldGroupsWithFields) {
            $this->fields = $getCustomFieldGroupsWithFields->fields;
        }

        $this->view = 'products.ajax.edit';

        if (request()->ajax()) {
            return $this->returnAjax($this->view);
        }

        return view('products.create', $this->data);

    }

    /**
     * @param  UpdateProductRequest $request
     * @param  int                  $id
     * @return array|void
     * @throws \Froiden\RestAPI\Exceptions\RelatedResourceNotFoundException
     */
    public function update(UpdateProductRequest $request, $id)
    {
        $product = Product::findOrFail($id)->withCustomFields();
        $this->editPermission = user()->permission('edit_product');

        abort_403(!($this->editPermission == 'all' || ($this->editPermission == 'added' && $product->added_by == user()->id)));

        $this->validateProductResources($request); // AFV (upgrade 6.0.19)

        $product->name = $request->name;
        $product->price = $request->price;

        if ($request->has('cost_price')) { // AFV (S8): only when the form sends the field
            $product->cost_price = $request->filled('cost_price') ? $request->cost_price : null;
        }

        $product->taxes = $request->tax ? json_encode($request->tax) : null;
        $product->hsn_sac_code = $request->hsn_sac_code;
        $product->sku = $request->sku;
        $product->unit_id = $request->unit_type;
        $product->description = trim_editor($request->description);
        $product->allow_purchase = ($request->purchase_allow == 'no') ? true : false;
        $product->downloadable = ($request->downloadable == 'true') ? true : false;
        $product->category_id = ($request->category_id) ? $request->category_id : null;
        $product->sub_category_id = ($request->sub_category_id) ? $request->sub_category_id : null;

        if (request()->hasFile('downloadable_file') && request()->downloadable == 'true') {
            Files::deleteFile($product->downloadable_file, ProductFiles::FILE_PATH);
            $product->downloadable_file = Files::uploadLocalOrS3(request()->downloadable_file, ProductFiles::FILE_PATH);
        }
        elseif (request()->downloadable == 'true' && $product->downloadable_file == null) {
            $product->downloadable = false;
        }

        // change default image
        if (!request()->hasFile('file')) {
            $product->default_image = request()->default_image;
        }

        $product->save();

        // To add custom fields data
        if ($request->custom_fields_data) {
            $product->updateCustomFieldData($request->custom_fields_data);
        }

        $this->syncProductResources($product, $request);

        return Reply::successWithData(__('messages.updateSuccess'), ['redirectUrl' => route('products.index'), 'defaultImage' => $request->default_image ?? 0]);
    }

    /**
     * AFV (upgrade 6.0.19): server-side checks for the product videos/documents - upstream validates nothing.
     * Links: http(s) only (the link is an <a href> on the product page that clients see - no javascript:/data:).
     * Files: the extensions of the form's accept list, at most the upload size from the app settings.
     */
    protected function validateProductResources(Request $request): void
    {
        $maxKb = max((int)(global_setting()->allowed_file_size ?? 0), 1) * 1024;

        $request->validate([
            'resources' => 'nullable|array|max:50',
            'resources.*.type' => 'nullable|in:' . ProductResource::TYPE_VIDEO . ',' . ProductResource::TYPE_DOCUMENT,
            'resources.*.source' => 'nullable|in:' . ProductResource::SOURCE_URL . ',' . ProductResource::SOURCE_FILE,
            'resources.*.title' => 'nullable|string|max:255',
            'resources.*.url' => 'exclude_if:resources.*.source,' . ProductResource::SOURCE_FILE . '|nullable|string|max:2000|url:http,https',
            'resources.*.file' => 'exclude_unless:resources.*.source,' . ProductResource::SOURCE_FILE . '|nullable|file|max:' . $maxKb
                . '|extensions:pdf,doc,docx,xls,xlsx,ppt,pptx,txt,zip,rar,mp4,webm,mov,avi',
        ], [
            'resources.*.file.extensions' => __('validation.mimes'), // validation.php has no "extensions" message
        ], [
            'resources.*.type' => __('app.type'),
            'resources.*.source' => __('app.source'),
            'resources.*.title' => __('app.title'),
            'resources.*.url' => __('app.url'),
            'resources.*.file' => __('ui-products.resources.file'),
        ]);
    }

    /**
     * Create or update product videos/documents from the request.
     */
    protected function syncProductResources(Product $product, Request $request): void
    {
        $resources = $request->input('resources', []);

        if (!is_array($resources)) {
            return;
        }

        foreach ($resources as $index => $resourceData) {
            $type = $resourceData['type'] ?? null;
            $source = $resourceData['source'] ?? ProductResource::SOURCE_URL;
            $title = trim($resourceData['title'] ?? '');
            $url = trim($resourceData['url'] ?? '');
            $resourceId = $resourceData['id'] ?? null;
            $uploadedFile = $request->file('resources.' . $index . '.file');

            if (!in_array($type, [ProductResource::TYPE_VIDEO, ProductResource::TYPE_DOCUMENT], true)) {
                continue;
            }

            if ($source === ProductResource::SOURCE_URL && $url === '' && !$resourceId) {
                continue;
            }

            if ($source === ProductResource::SOURCE_FILE && !$uploadedFile && !$resourceId) {
                continue;
            }

            $resource = $resourceId
                ? ProductResource::where('product_id', $product->id)->find($resourceId)
                : new ProductResource();

            if (!$resource) {
                continue;
            }

            $resource->product_id = $product->id;
            // AFV (upgrade 6.0.19): ProductResource has no observer that sets company_id - without it CompanyScope hides the row
            $resource->company_id = $product->company_id;
            $resource->type = $type;
            $resource->title = $title !== '' ? $title : null;
            $resource->source = $source;
            $resource->added_by = $resource->added_by ?: user()->id;

            if ($source === ProductResource::SOURCE_URL) {
                if ($resource->exists && $resource->hashname) {
                    Files::deleteFile($resource->hashname, ProductResource::FILE_PATH);
                }

                $resource->url = $url !== '' ? $url : $resource->url;
                $resource->filename = null;
                $resource->hashname = null;
                $resource->size = null;
            } elseif ($uploadedFile) {
                if ($resource->exists && $resource->hashname) {
                    Files::deleteFile($resource->hashname, ProductResource::FILE_PATH);
                }

                $resource->url = null;
                $resource->filename = $uploadedFile->getClientOriginalName();
                $resource->hashname = Files::uploadLocalOrS3($uploadedFile, ProductResource::FILE_PATH);
                $resource->size = (string) $uploadedFile->getSize();
            }

            if ($source === ProductResource::SOURCE_URL && empty($resource->url)) {
                continue;
            }

            if ($source === ProductResource::SOURCE_FILE && empty($resource->hashname)) {
                continue;
            }

            $resource->save();
        }
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {
        $products = Product::findOrFail($id);
        $this->deletePermission = user()->permission('delete_product');
        abort_403(!($this->deletePermission == 'all' || ($this->deletePermission == 'added' && $products->added_by == user()->id)));

        $products->delete();

        return Reply::successWithData(__('messages.deleteSuccess'), ['redirectUrl' => route('products.index')]);
    }

    /**
     * XXXXXXXXXXX
     *
     * @return array
     */
    public function applyQuickAction(Request $request)
    {
        switch ($request->action_type) {
        case 'delete':
            $this->deleteRecords($request);

            return Reply::success(__('messages.deleteSuccess'));
        case 'change-purchase':
            $this->allowPurchase($request);

            return Reply::success(__('messages.updateSuccess'));
        default:
            return Reply::error(__('messages.selectAction'));
        }
    }

    protected function deleteRecords($request)
    {
        abort_403(user()->permission('delete_product') != 'all');

        Product::whereIn('id', explode(',', $request->row_ids))->forceDelete();
    }

    protected function allowPurchase($request)
    {
        abort_403(user()->permission('edit_product') != 'all');

        Product::whereIn('id', explode(',', $request->row_ids))->update(['allow_purchase' => $request->status]);
    }

    public function addCartItem(Request $request)
    {
        $newItem = $request->productID;

           $orderExist = OrderCart::where('product_id', '=', $newItem)->where('client_id', user()->id)->exists();
           $quantity = 1;

        if ($orderExist == true) {
                $orderCartUpdate = OrderCart::where('product_id', '=', $newItem)->where('client_id', user()->id)->first();

                $quantity = ($request->has('quantity')) ? $request->quantity : $orderCartUpdate->quantity + 1;

                OrderCart::where('product_id', '=', $newItem)->where('client_id', user()->id)->update(
                    [
                        'quantity' => $quantity,
                        'amount' => $orderCartUpdate->unit_price * $quantity,
                    ]
                );
            $productDetails[] = OrderCart::where('product_id', '=', $newItem)->first();

        } else {
            $productDetails = Product::where('id', $newItem)->first();

            $product = new OrderCart();
            $product->product_id = $productDetails->id;
            $product->client_id = user()->id;
            $product->item_name = $productDetails->name;
            $product->description = $productDetails->description;
            $product->type = 'item';
            $product->quantity = $quantity;
            $product->unit_price = $productDetails->price;
            $product->amount = $productDetails->price;
            $product->taxes = $productDetails->taxes;
            $product->unit_id = $productDetails->unit_id;
            $product->hsn_sac_code = $productDetails->hsn_sac_code;
            $product->save();
            $productDetails = $product;

        }

        $cartProduct = OrderCart::where('client_id', user()->id)->sum('quantity');

        if (!$request->has('cartType')) {

            return response(Reply::successWithData(__('messages.recordSaved'), ['status' => 'success', 'cartProduct' => $cartProduct, 'productItems' => $productDetails]));

        }

    }

    public function removeCartItem(Request $request, $id)
    {
        // AFV (upgrade 6.0.19): only the current user's cart - order_cart has no CompanyScope and upstream deleted
        // any cart (by client id) or cart row (by id), also of another company; every caller in the UI sends its own ids
        if ($request->type == 'all_data') {
            abort_403($id != user()->id);

            $products = OrderCart::where('client_id', $id)->delete();
            $productDetails = OrderCart::where('client_id', $id)->count();

        } else {

            $products = OrderCart::where('client_id', user()->id)->findOrFail($id);
            $products->delete();

            $productDetails = OrderCart::where('id', $id)->where('client_id', user()->id)->get();
        }


        return response(Reply::successWithData(__('messages.deleteSuccess'), ['status' => 'success', 'productItems' => $productDetails ]))->cookie('productDetails', json_encode($productDetails));

    }

    public function emptyCart()
    {

        $this->view = 'products.ajax.empty_cart';

        if (request()->ajax()) {
            return $this->returnAjax($this->view);
        }

        return view('products.create', $this->data);

    }

    public function cart(Request $request)
    {
        abort_403(!in_array('client', user_roles()));

        $this->lastOrder = Order::lastOrderNumber() + 1;
        $this->invoiceSetting = invoice_setting();
        $this->taxes = Tax::all();

        $this->products = OrderCart::where('client_id', '=', user()->id)->get();

        $this->view = 'products.ajax.cart';

        if (request()->ajax()) {
            return $this->returnAjax($this->view);
        }

        return view('products.create', $this->data);
    }

    public function allProductOption()
    {
        return Reply::dataOnly([
            'products' => Product::optionsForSelect(Product::with('files')->orderBy('name')->get()),
        ]);
    }

    public function importProduct()
    {
        $this->pageTitle = __('app.importExcel') . ' ' . __('app.menu.product');

        $this->addPermission = user()->permission('add_product');
        abort_403(!in_array($this->addPermission, ['all', 'added']));

        $this->view = 'products.ajax.import';

        if (request()->ajax()) {
            return $this->returnAjax($this->view);
        }

        return view('products.create', $this->data);
    }

    public function importStore(ImportRequest $request)
    {
        $rvalue = $this->importFileProcess($request, ProductImport::class);

        if($rvalue == 'abort'){
            return Reply::error(__('messages.abortAction'));
        }

        $view = view('products.ajax.import_progress', $this->data)->render();

        return Reply::successWithData(__('messages.importUploadSuccess'), ['view' => $view]);
    }

    public function importProcess(ImportProcessRequest $request)
    {
        $batch = $this->importJobProcess($request, ProductImport::class, ImportProductJob::class);

        return Reply::successWithData(__('messages.importProcessStart'), ['batch' => $batch]);
    }

}
