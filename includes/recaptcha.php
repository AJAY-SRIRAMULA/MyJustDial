<?php

function getGoogleRecaptchaCredentials(PDO $pdo): array
{
    $credentials = [
        'site_key' => '',
        'secret_key' => '',
    ];

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
