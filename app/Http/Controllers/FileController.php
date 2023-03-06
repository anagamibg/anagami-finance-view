<?php

namespace App\Http\Controllers;

use App\Helper\Common;

class FileController extends Controller
{

    /**
     * AFV (upgrade 6.0.19): the only paths this public route ever serves are the masked images of
     * App\Traits\HasMaskImage (S3 storage only). Common::encryptDecrypt() still accepts the legacy key that is the
     * same in every AFV install, so anyone can forge a {path}: without this list the route signs a temporary
     * URL for ANY object of the bucket (other companies' files).
     */
    private const MASKED_IMAGE_DIRS = ['app-logo', 'avatar', 'favicon', 'login-background', 'offline-method', 'invoice-template', 'front/homepage-background', 'front/seo-detail'];

    private const MASKED_IMAGE_EXTENSIONS = ['png', 'jpg', 'jpeg', 'gif', 'webp', 'bmp', 'ico'];

    public function getFile($type, $path)
    {
        abort_if(!in_array($type, ['file', 'image']), 404);

        try {
            $path = str($path)->replace('_masked.png', '')->__toString();
            $decrypted = Common::assertSafeStoragePath(Common::encryptDecrypt($path, 'decrypt'));

            // AFV (upgrade 6.0.19): masked images only (see MASKED_IMAGE_DIRS)
            abort_if(
                !in_array(dirname($decrypted), self::MASKED_IMAGE_DIRS, true)
                || !in_array(strtolower(pathinfo($decrypted, PATHINFO_EXTENSION)), self::MASKED_IMAGE_EXTENSIONS, true),
                404
            );

            return response()->redirectTo(asset_url_local_s3($decrypted));
        } catch (\Exception $e) {
            abort(404);
        }

    }

}
