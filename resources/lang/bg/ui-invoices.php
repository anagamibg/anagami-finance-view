<?php

// AFV (upgrade 6.0.19): invoice texts that AFV 6.0.19 uses without a key in its own lang files
// (invoice template 6 header/footer images) and the CUS_000329 package limit message.
return [
    'template' => [
        'banners_title' => 'Горна и долна част на шаблон 6',
        'top_image' => 'Изображение в горната част',
        'top_image_info' => 'Показва се в цялата ширина в горната част на PDF с шаблон 6. Използвайте широко изображение PNG, JPG, BMP или WEBP до 5 MB.',
        'bottom_image' => 'Изображение в долната част',
        'bottom_image_info' => 'Показва се в цялата ширина в долната част на всяка страница на PDF с шаблон 6. Използвайте широко изображение PNG, JPG, BMP или WEBP до 5 MB.',
    ],

    'limits' => [
        'max_invoices_reached' => 'Достигнахте максималния брой фактури, разрешен от Вашия план. Надградете плана, за да добавяте още фактури.',
    ],
];
