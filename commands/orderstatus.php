<?php
$ch = curl_init();
$_GET['id'] = "c27c28c0-5fef-4a12-aca4-65b34ec889bc";
$order_id = $_GET['id'];
curl_setopt_array($ch, [
    CURLOPT_URL => 'https://asklepi0s.cc/api/orders/'.$_GET['id'],
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_COOKIE => 'auth_token=eyJhbGciOiJIUzI1NiJ9.eyJ1c2VySWQiOiJkNTgwMjA2OS0zNGEzLTRmZmMtODM3Zi05OGZiNDVmZDU4MzciLCJlbWFpbCI6InRhbmRyZXpvbmUxMjNAZ21haWwuY29tIiwicm9sZSI6ImN1c3RvbWVyIiwiaWF0IjoxNzc5Nzg4NDYwLCJleHAiOjE3ODAzOTMyNjB9._qoZyc4BzYWlTkU97zNaHbY5AT1buHxLNjsZNQICd4A',
    
    // 1. Add a User-Agent (Some APIs block empty user-agents with a 403)
    CURLOPT_USERAGENT => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36',
    
    // 2. Uncomment the two lines below ONLY if you are testing locally and getting an SSL error
    // CURLOPT_SSL_VERIFYPEER => false,
    // CURLOPT_SSL_VERIFYHOST => false,
]);

$response = curl_exec($ch);

// 3. Check for transport-level errors (e.g., cannot connect, timeout)
if (curl_errno($ch)) {
    echo 'cURL Error: ' . curl_error($ch);
} else {
    // 4. Check the HTTP status code returned by the server
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    
    if ($http_code === 200) {
        echo $response;
        file_put_contents(__DIR__ . '/../api/orders/' . $order_id . '.json', $response);
    } else {
        echo "The server responded with an error code, but an empty body.";
    }
}
curl_close($ch);