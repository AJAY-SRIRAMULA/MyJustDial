<?php

function getGoogleRecaptchaCredentials(PDO $pdo): array
{
    $credentials = [
        'site_key' => '',
        'secret_key' => '',
    ];

    $localPath = __DIR__ . '/../config/config.local.php';
    $local = is_file($localPath) ? require $localPath : [];
    $local = is_array($local) ? ($local['recaptcha'] ?? []) : [];
    $site = trim((string)(getenv('RECAPTCHA_SITE_KEY') ?: ($local['site_key'] ?? '')));
    $secret = trim((string)(getenv('RECAPTCHA_SECRET_KEY') ?: ($local['secret_key'] ?? '')));
    if ($site !== '' && $secret !== '') {
        return ['site_key' => $site, 'secret_key' => $secret];
    }

    if (!tableExists($pdo, 'website_settings')) {
        return $credentials;
    }

    $statement = $pdo->prepare(
        "SELECT field, result
         FROM website_settings
         WHERE field IN ('recaptcha_site_key', 'recaptcha_secret_key')"
    );
    $statement->execute();

    foreach ($statement->fetchAll(PDO::FETCH_ASSOC) as $setting) {
        if ($setting['field'] === 'recaptcha_site_key') {
            $credentials['site_key'] = trim((string)$setting['result']);
        } elseif ($setting['field'] === 'recaptcha_secret_key') {
            $credentials['secret_key'] = trim((string)$setting['result']);
        }
    }

    return $credentials;
}

function verifyGoogleRecaptchaV2(
    string $responseToken,
    string $secretKey,
    string $requestHost
): bool {
    $responseToken = trim($responseToken);
    $secretKey = trim($secretKey);

    if ($responseToken === '' || $secretKey === '') {
        return false;
    }

    $payload = http_build_query([
        'secret' => $secretKey,
        'response' => $responseToken,
        'remoteip' => $_SERVER['REMOTE_ADDR'] ?? '',
    ]);

    $verificationResponse = false;

    if (function_exists('curl_init')) {
        $curl = curl_init('https://www.google.com/recaptcha/api/siteverify');
        curl_setopt_array($curl, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $payload,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_TIMEOUT => 10,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
        ]);

        $verificationResponse = curl_exec($curl);
        $httpStatus = (int)curl_getinfo($curl, CURLINFO_HTTP_CODE);
        curl_close($curl);

        if ($verificationResponse === false || $httpStatus !== 200) {
            return false;
        }
    } else {
        $context = stream_context_create([
            'http' => [
                'method' => 'POST',
                'header' => "Content-Type: application/x-www-form-urlencoded\r\n",
                'content' => $payload,
                'timeout' => 10,
                'ignore_errors' => true,
            ],
            'ssl' => [
                'verify_peer' => true,
                'verify_peer_name' => true,
            ],
        ]);

        $verificationResponse = @file_get_contents(
            'https://www.google.com/recaptcha/api/siteverify',
            false,
            $context
        );

        if ($verificationResponse === false) {
            return false;
        }
    }

    $result = json_decode($verificationResponse, true);

    if (!is_array($result) || empty($result['success'])) {
        return false;
    }

    $expectedHost = strtolower(trim(preg_replace('/:\d+$/', '', $requestHost)));
    $verifiedHost = strtolower(trim((string)($result['hostname'] ?? '')));

    if ($expectedHost !== '' && $verifiedHost !== '' && $expectedHost !== $verifiedHost) {
        return false;
    }

    return $verifiedHost !== '';
}

function renderConfiguredCaptcha(PDO $pdo): string
{
    $keys = getGoogleRecaptchaCredentials($pdo);
    if ($keys['site_key'] === '' || $keys['secret_key'] === '') {
        return '<p role="alert">CAPTCHA is not configured. Please contact the site administrator.</p>';
    }
    static $scriptAdded = false;
    $html = '<div class="g-recaptcha" data-sitekey="' . e($keys['site_key']) . '"></div>';
    if (!$scriptAdded) {
        $scriptAdded = true;
        $html .= '<script>window.myJustDialCaptchaReady=function(){function render(){document.querySelectorAll(".g-recaptcha[data-sitekey]").forEach(function(el){if(el.dataset.captchaRendered)return;grecaptcha.render(el,{sitekey:el.dataset.sitekey});el.dataset.captchaRendered="1";});}if(document.readyState==="loading"){document.addEventListener("DOMContentLoaded",render,{once:true});}else{render();}};</script>'
            . '<script src="https://www.google.com/recaptcha/api.js?onload=myJustDialCaptchaReady&amp;render=explicit" async defer></script>';
    }
    return $html;
}

function configuredCaptchaError(PDO $pdo, array $fields): string
{
    foreach ($fields as $field) {
        if ((string)($field['field_input_view'] ?? '1') === '0' || formFieldInternalType((string)$field['field_type']) !== 'captcha') continue;
        $keys = getGoogleRecaptchaCredentials($pdo);
        $token = $_POST['g-recaptcha-response'] ?? '';
        if (!is_string($token) || !verifyGoogleRecaptchaV2($token, $keys['secret_key'], (string)($_SERVER['HTTP_HOST'] ?? ''))) {
            return 'Please complete the CAPTCHA verification. If unavailable, contact the site administrator.';
        }
        break;
    }
    return '';
}
