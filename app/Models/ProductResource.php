<?php

namespace App\Models;

use App\Traits\HasCompany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductResource extends BaseModel
{
    use HasCompany;

    public const FILE_PATH = 'product-resources';

    public const TYPE_VIDEO = 'video';

    public const TYPE_DOCUMENT = 'document';

    public const SOURCE_URL = 'url';

    public const SOURCE_FILE = 'file';

    protected $guarded = ['id'];

    protected $appends = ['file_url', 'icon', 'link_url'];

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function getFileUrlAttribute(): ?string
    {
        if ($this->source === self::SOURCE_FILE && $this->hashname) {
            return asset_url_local_s3(self::FILE_PATH . '/' . $this->hashname);
        }

        return null;
    }

    public function getLinkUrlAttribute(): ?string
    {
        if ($this->source === self::SOURCE_URL) {
            // AFV (upgrade 6.0.19): only http(s) links reach the href (no javascript:/data:)
            return preg_match('#^https?://#i', (string)$this->url) ? $this->url : null;
        }

        return $this->file_url;
    }

    public function getIconAttribute(): string
    {
        $haystack = strtolower(($this->url ?? '') . ' ' . ($this->filename ?? '') . ' ' . ($this->title ?? ''));

        if ($this->type === self::TYPE_VIDEO) {
            if (str_contains($haystack, 'youtu')) {
                return 'fa-youtube';
            }

            if (str_contains($haystack, 'drive.google') || str_contains($haystack, 'docs.google')) {
                return 'fa-google-drive';
            }

            return 'fa-video';
        }

        if (str_contains($haystack, 'pdf')) {
            return 'fa-file-pdf';
        }

        return 'fa-file-alt';
    }

    public function getDisplayTitleAttribute(): string
    {
        if (!empty($this->title)) {
            return $this->title;
        }

        if ($this->source === self::SOURCE_FILE && $this->filename) {
            return $this->filename;
        }

        return $this->type === self::TYPE_VIDEO ? __('app.productVideo') : __('app.productDocument');
    }
}
