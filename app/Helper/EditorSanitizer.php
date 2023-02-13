<?php

namespace App\Helper;

use HTMLPurifier;
use HTMLPurifier_Config;

class EditorSanitizer
{
    private static ?HTMLPurifier $purifier = null;

    public static function clean(?string $html): string
    {
        if ($html === null || $html === '') {
            return '';
        }

        return self::getPurifier()->purify($html);
    }

    private static function getPurifier(): HTMLPurifier
    {
        if (self::$purifier !== null) {
            return self::$purifier;
        }

        $config = HTMLPurifier_Config::createDefault();

        $config->set('HTML.Allowed', implode(',', [
            'p[class|style]',
            'br',
            'strong',
            'b',
            'em',
            'i[class]', // AFV (upgrade 6.0.19): was 'i' - x-cards.data-row html="true" lost the class of the fa icons (status dots, note colour)
            'u',
            's',
            'strike',
            'ul[class]',
            'ol[class]',
            'li[class]',
            'a[href|target|rel|class|style]',
            'h1',
            'h2',
            'h3',
            'h4',
            'h5',
            'h6',
            'blockquote[class|style]',
            'span[class|style|data-id|data-value|data-index|data-denotation-char|data-link]', // AFV (upgrade 6.0.19): Quill mentions (was 'span[class|style]')
            'pre[class|spellcheck]', // AFV (upgrade 6.0.19): Quill code block
            'img[src|alt|width|height|class|style]',
            'iframe[src|class|frameborder|width|height|title]',
        ]));

        $config->set('CSS.AllowedProperties', [
            'color',
            'background-color',
            'background',
            'text-align',
            'direction',
        ]);

        $config->set('URI.AllowedSchemes', [
            'http' => true,
            'https' => true,
            'mailto' => true,
            'tel' => true,
            'data' => true, // AFV (upgrade 6.0.19): pasted images (HTMLPurifier allows only data:image/png|gif|jpeg)
        ]);

        // AFV (upgrade 6.0.19): classes that resources/js/custom.js binds to load this.href into the right modal
        // (.html() of the response) - in user content they would load and run a foreign page with one click
        $config->set('Attr.ForbiddenClasses', ['openRightModal', 'inModal']);
        // AFV (upgrade 6.0.19): no generated alt (it repeated the whole base64 of a data: image)
        $config->set('Attr.DefaultImageAlt', '');

        $config->set('Attr.AllowedFrameTargets', ['_blank', '_self']);
        $config->set('HTML.TargetBlank', true);
        $config->set('HTML.SafeIframe', true);
        $config->set('URI.SafeIframeRegexp', '%^(https?:)?//(www\.youtube\.com/embed/|player\.vimeo\.com/video/)%');

        $cachePath = storage_path('app/htmlpurifier');

        if (! is_dir($cachePath)) {
            @mkdir($cachePath, 0755, true);
        }

        $config->set('Cache.SerializerPath', $cachePath);

        // AFV (upgrade 6.0.19): HTMLPurifier does not know data-* / spellcheck - declare them (cached by ID/Rev)
        $config->set('HTML.DefinitionID', 'databackd-editor');
        $config->set('HTML.DefinitionRev', 3);

        if ($def = $config->maybeGetRawHTMLDefinition()) {
            foreach (['data-id' => 'Number', 'data-value' => 'Text', 'data-index' => 'Text', 'data-denotation-char' => 'Text', 'data-link' => 'URI'] as $attr => $type) {
                $def->addAttribute('span', $attr, $type);
            }

            $def->addAttribute('pre', 'spellcheck', 'Enum#true,false');
        }

        self::$purifier = new HTMLPurifier($config);

        return self::$purifier;
    }
}
