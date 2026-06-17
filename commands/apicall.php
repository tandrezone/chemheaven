<?php
namespace Tandrezone\Commands;

use Tandrezone\Chemheaven\Services\ImageManipulator;

class apicall {
    public static function call($url, $auth_token) {
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_COOKIE => 'auth_token=' . $auth_token,
            CURLOPT_USERAGENT => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36',
        ]);

        $response = curl_exec($ch);

        if (curl_errno($ch)) {
            echo 'cURL Error: ' . curl_error($ch);
        } else {
            $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            if ($http_code === 200) {
                $data = json_decode($response, true);
                
                file_put_contents(__DIR__ . '/../api/products_live.json', $response);
                return $response;
            } else {
                echo "The server responded with an error code, but an empty body.";
                return null;
            }
        }
        curl_close($ch);
    }
    public static function double() {
        $data = json_decode(file_get_contents(__DIR__ . '/../api/products_live.json'), true);
        if (isset($data['products']) && is_array($data['products'])) {
            foreach ($data['products'] as &$product) {
                self::createImage($product);
                if (isset($product['variants']) && is_array($product['variants'])) {
                    foreach ($product['variants'] as &$variant) {
                        if (isset($variant['price'])) {
                            // Double the variant price
                            $variant['price'] = $variant['price'] * 2;
                        }
                    }
                }
            }
            // Unset the reference pointer
            unset($product); 
            unset($variant); 

            // Save the modified data back to the file
            file_put_contents(__DIR__ . '/../api/products_doubled.json', json_encode($data, JSON_PRETTY_PRINT));
        } else {
                echo "The products data is not in the expected format.";
            
        }
    }

    public static function createImage(&$product) {
            $product['image'] = \Tandrezone\Chemheaven\Services\ImageManipulator::createTextImageBase64(
                $product['name'], 
                __DIR__ . '/../src/ImageManipulator/assets/card_bg.png', 
                __DIR__ . '/../src/ImageManipulator/assets/Roboto-Regular.ttf'
            );
    }
}


apicall::call('https://asklepi0s.cc/api/products', 'your_auth_token_here');
apicall::double();