<?php
function categoryStockRequest(string $url, bool $api, int $limit): string
{
    if (!function_exists('curl_init')) {
        throw new RuntimeException('Enable the PHP cURL extension in XAMPP.');
    }
    $key = getenv('PEXELS_API_KEY');
    if ($api && !$key) {
        throw new RuntimeException('Set PEXELS_API_KEY in the Apache environment and restart Apache.');
    }
    $body = '';
    $curl = curl_init($url);
    curl_setopt_array($curl, [
        CURLOPT_FOLLOWLOCATION => false,
        CURLOPT_CONNECTTIMEOUT => 8,
        CURLOPT_TIMEOUT => 25,
        CURLOPT_PROTOCOLS => CURLPROTO_HTTPS,
        CURLOPT_HTTPHEADER => $api ? ['Authorization: '.$key] : [],
        CURLOPT_WRITEFUNCTION => static function ($handle, $chunk) use (&$body, $limit) {
            if (strlen($body) + strlen($chunk) > $limit) return 0;
            $body .= $chunk;
            return strlen($chunk);
        },
    ]);
    $ok = curl_exec($curl);
    $status = curl_getinfo($curl, CURLINFO_HTTP_CODE);
    curl_close($curl);
    if (!$ok || $status !== 200) {
        throw new RuntimeException('Pexels request failed (HTTP '.$status.'). Check API key, network and image size.');
    }
    return $body;
}
function categoryStockSearch(array $input): array
{
    $query = trim((string)($input['query'] ?? ''));
    if ($query === '' || strlen($query) > 200) throw new RuntimeException('Enter a search keyword of up to 200 characters.');
    $orientation = (string)($input['orientation'] ?? '');
    $params = ['query'=>$query, 'page'=>max(1,min(100,(int)($input['page'] ?? 1))), 'per_page'=>24];
    if (in_array($orientation, ['landscape','portrait','square'], true)) $params['orientation'] = $orientation;
    $data = json_decode(categoryStockRequest('https://api.pexels.com/v1/search?'.http_build_query($params), true, 2000000), true, 512, JSON_THROW_ON_ERROR);
    $photos = [];
    foreach ($data['photos'] ?? [] as $photo) {
        $photos[] = ['id'=>$photo['id'], 'preview'=>$photo['src']['medium'], 'url'=>$photo['url'], 'alt'=>$photo['alt'] ?? '', 'photographer'=>$photo['photographer'], 'width'=>$photo['width'], 'height'=>$photo['height']];
    }
    return ['photos'=>$photos, 'more'=>!empty($data['next_page']) && $params['page'] < 100];
}
function categoryStockSave(string $id, string $size): string
{
    if (!preg_match('/^[1-9][0-9]{0,15}$/D', $id)) throw new RuntimeException('Invalid stock photo.');
    $width = ['small'=>400,'medium'=>650,'large'=>1200][$size] ?? 650;
    $photo = json_decode(categoryStockRequest('https://api.pexels.com/v1/photos/'.$id, true, 2000000), true, 512, JSON_THROW_ON_ERROR);
    $original = parse_url($photo['src']['original'] ?? '');
    if (($original['scheme'] ?? '') !== 'https' || ($original['host'] ?? '') !== 'images.pexels.com' || !preg_match('~^/photos/[0-9]+/[a-zA-Z0-9._-]+$~D', $original['path'] ?? '')) throw new RuntimeException('Invalid provider image URL.');
    $bytes = categoryStockRequest('https://images.pexels.com'.$original['path'].'?auto=compress&cs=tinysrgb&w='.$width, false, 5000000);
    $mime = (new finfo(FILEINFO_MIME_TYPE))->buffer($bytes);
    $ext = ['image/jpeg'=>'jpg','image/png'=>'png','image/webp'=>'webp'][$mime] ?? null;
    if (!$ext || !getimagesizefromstring($bytes)) throw new RuntimeException('Invalid image received.');
    $dir = __DIR__.'/../public/assets/category-images';
    if (!is_dir($dir) && !mkdir($dir,0755,true)) throw new RuntimeException('Unable to create image folder.');
    $name = bin2hex(random_bytes(16)).'.'.$ext;
    if (file_put_contents($dir.'/'.$name,$bytes) === false) throw new RuntimeException('Unable to save image.');
    return 'public/assets/category-images/'.$name;
}
