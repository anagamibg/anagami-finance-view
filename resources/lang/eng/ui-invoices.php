<?php

// AFV (upgrade 6.0.19): invoice texts that AFV 6.0.19 uses without a key in its own lang files
// (invoice template 6 header/footer images) and the CUS_000329 package limit message.
return [
    'template' => [
        'banners_title' => 'Template 6 Header and Footer',
        'top_image' => 'Header image',
        'top_image_info' => 'Shown full width at the top of the template 6 PDF. Use a wide PNG, JPG, BMP or WebP image up to 5 MB.',
        'bottom_image' => 'Footer image',
        'bottom_image_info' => 'Shown full width at the bottom of every page of the template 6 PDF. Use a wide PNG, JPG, BMP or WebP image up to 5 MB.',
    ],

    'limits' => [
        'max_invoices_reached' => 'You have reached the maximum number of invoices allowed in your plan. Please upgrade your plan to add more invoices.',
    ],
];
