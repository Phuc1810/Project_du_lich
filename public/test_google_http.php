<?php
$url = "https://oauth2.googleapis.com/tokeninfo?id_token=abc";

$ch = curl_init($url);
curl_setopt_array($ch, [
  CURLOPT_RETURNTRANSFER => true,
  CURLOPT_TIMEOUT => 10,
  CURLOPT_SSL_VERIFYPEER => true,
  CURLOPT_SSL_VERIFYHOST => 2,
]);
$resp = curl_exec($ch);
$err  = curl_error($ch);
$code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

echo "http_code: $code\n";
echo "curl_error: $err\n";
echo "resp_first_200: " . substr((string)$resp, 0, 200) . "\n";
