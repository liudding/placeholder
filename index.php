<?php

use RingCentral\Psr7\Response;

/*
To enable the initializer feature (https://help.aliyun.com/document_detail/89029.html)
please implement the initializer function as below：
function initializer($context) {
    echo 'initializing' . PHP_EOL;
}
*/

function handler($request, $context): Response {

    $path       = $request->getAttribute('path');
    $queries    = $request->getQueryParams();

    if ($path === '/favicon.ico') {
        return respondNotFound();
    }


    $params = getParams($request);

    return respondImage(drawImage($params));
}

function drawImage($params)
{
    $imagick = new \Imagick();
    $imagick->newImage($params['size']['width'], $params['size']['height'], new ImagickPixel($params['background_color']));
    $imagick->setCompressionQuality(100);

    $draw = new \ImagickDraw();

    $draw->setFillColor(new \ImagickPixel($params['text_color']));

    $draw->setStrokeWidth(2);
    $draw->setFontSize(48);
    $draw->setFont(getFont());

    $metrics = ajustText($imagick, $draw, $params['text']);

    $x = ($params['size']['width'] - $metrics['width'] ) / 2;
    $y = ($params['size']['height']) / 2 ;

    $draw->setFontSize($metrics['fontSize']);
    $draw->annotation($x, $y, $params['text']);
    
   
    $imagick->setImageFormat("png");
    $imagick->drawImage($draw);

    return $imagick->getImageBlob();
}

function getTextBlock($imageWith, $imageHeight)
{
    $padding = 10;

    return [
        'width' => $imageWith - $padding * 2,
        'height' => $imageHeight - $padding * 2
    ];
}

function ajustText($imagick, $draw, $text)
{
    $textBlockWidth = getTextBlock($imagick->getImageWidth(), $imagick->getImageHeight())['width'];

    $fontsize = 48;
    $width = PHP_INT_MAX;
    $height = 0;

    while ($fontsize >= 5) {
        $metrics = $imagick->queryFontMetrics($draw, $text);

        $width = $metrics['textWidth'];
        $height = $metrics['textHeight'];

        if ($width > $textBlockWidth) {
            $fontsize -= 2;
            $draw->setFontSize($fontsize);
        } else {
            break;
        }
    }

    return [
        'fontSize' => $fontsize,
        'width' => $width,
        'height' => $height
    ];
}

function getFont()
{
    return '/usr/share/fonts/truetype/wqy/wqy-microhei.ttc';
}

function respondJson($data)
{
    return new Response(200, [
          'content-type'=> 'application/json;charset=UTF-8'
        ],
        json_encode($data)
        );
}

function respondImage($image)
{
     return new Response(
        200,
        array(
            'Accept-Ranges' => 'bytes',
            'Content-Type' => 'image/png',
            'Cache-Control' => 'max-age=604800'
        ),
        $image
    );
}

function respondRedirect()
{
    return new Response(307, [
        'Location' => ''
    ], '');
}

function respondNotFound()
{
    return new Response(404, [], '');
}






function getParams($request)
{
    return array_merge(getDefaultParams(), parseRequestParams($request));
}

function getDefaultParams()
{
    return [
        'size' => [
            'width' => 300,
            'height' => 300
        ],
        'text_color' => '#ffffff',
        'background_color' => '#cccccc',
        'format' => 'png',

        'text' => 'menco.cn'
    ];
}

function parseRequestParams($request)
{
    // size, background color, text color, formate

    $path       = strtolower($request->getAttribute('path'));
    $queries    = $request->getQueryParams();

    $parts = explode('/', $path);

    $params = [];

    if (isset($queries['text'])) {
        $params['text'] = $queries['text'];
    }

    foreach ($parts as $part) {
        if (checkIsSize($part)) {
            $params['size'] = parseSize($part);
        } else if ($color = parseColor($part)) {
            if (isset($params['background_color'])) {
                $params['text_color'] = $color;
            } else {
                $params['background_color'] = $color;
            }
        }
    }

   
    return $params;
}

function checkIsSize($str)
{
    return !!preg_match('/^\d+([x*]\d+)?$/', $str);
}

function parseColor($str)
{
    if (preg_match('/^#?([a-fA-F0-9]{6}|[a-fA-F0-9]{3})$/', $str)) {
        return strpos('#', $str) !== false ? $str : '#'.$str;
    }

    if (preg_match('/^[rR][gG][Bb][Aa]?[\(]([\s]*(2[0-4][0-9]|25[0-5]|[01]?[0-9][0-9]?),){2}[\s]*(2[0-4][0-9]|25[0-5]|[01]?[0-9][0-9]?),?[\s]*(0\.\d{1,2}|1|0)?[\)]{1}$/', $str)) {
        return $str;
    }

    $colors = ['black', 'white', 'gray', 'yellow', 'blue', 'red', 'green', 'pink', 'purple', 'orange'];

    return array_search($str, $colors) !== false ? $str : null;
}

function parseSize($str)
{   
    if (strpos($str, 'x') !== false) {
        [$width, $height] = explode('x', $str);
    } else if (strpos($str, '*') !== false) {
        [$width, $height] = explode('*', $str);
    } else {
        $width = +$str;
    }

    if (empty($width)) {
        return null;
    }

    return [
        'width' => $width,
        'height' => $height ?? $width
    ];
}
