<?php
function razorpayTestKey(): string {
    $key = (string)getenv('RAZORPAY_KEY_ID');
    if (!str_starts_with($key, 'rzp_test_') || !getenv('RAZORPAY_KEY_SECRET')) {
        throw new RuntimeException('Razorpay test credentials are not configured.');
    }
    return $key;
}
function razorpayTestRequest(string $path, ?array $body = null): array {
    if (!function_exists('curl_init')) throw new RuntimeException('PHP cURL is disabled. Enable extension=curl and restart Apache.');
    $ch = curl_init('https://api.razorpay.com/v1/' . $path);
    curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_USERPWD => razorpayTestKey() . ':' . getenv('RAZORPAY_KEY_SECRET'), CURLOPT_TIMEOUT => 25, CURLOPT_CONNECTTIMEOUT => 10, CURLOPT_HTTPHEADER => ['Content-Type: application/json']]);
    if ($body !== null) { curl_setopt($ch, CURLOPT_POST, true); curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($body)); }
    $raw = curl_exec($ch);
    $curlError = curl_errno($ch);
    $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    $data = json_decode((string)$raw, true);
    if ($curlError) throw new RuntimeException('Razorpay connection failed (cURL error ' . $curlError . '). Check network and CA certificate configuration.');
    if ($status === 401) throw new RuntimeException('Razorpay rejected the test credentials. Check the key pair.');
    if ($status < 200 || $status >= 300 || !is_array($data)) throw new RuntimeException('Payment gateway request failed (HTTP ' . $status . ').');
    return $data;
}
function razorpayPlanAmount(array $plan, string $cycle): int {
    if (!in_array($cycle, ['monthly','yearly'], true)) throw new RuntimeException('Invalid billing period.');
    $amount = (int)round((float)$plan[$cycle . '_amount'] * 100);
    if ($amount < 100) throw new RuntimeException('Invalid paid plan amount.');
    return $amount;
}
function razorpayVerifySignup(array $plan, string $email): array {
    $order = (string)($_POST['razorpay_order_id'] ?? '');
    $payment = (string)($_POST['razorpay_payment_id'] ?? '');
    $signature = (string)($_POST['razorpay_signature'] ?? '');
    $pending = $_SESSION['razorpay_signup_orders'][$order] ?? null;
    if (!$pending || !preg_match('/^pay_[A-Za-z0-9]+$/D', $payment) || $pending['plan'] !== (int)$plan['subscription_id'] || $pending['email'] !== strtolower($email)) throw new RuntimeException('Payment does not match this signup.');
    razorpayTestKey();
    if (!hash_equals(hash_hmac('sha256', $order . '|' . $payment, (string)getenv('RAZORPAY_KEY_SECRET')), $signature)) throw new RuntimeException('Invalid payment signature.');
    $amount = razorpayPlanAmount($plan, $pending['cycle']);
    $record = razorpayTestRequest('payments/' . rawurlencode($payment));
    if ($record['order_id'] !== $order || $record['status'] !== 'captured' || (int)$record['amount'] !== $amount || $record['currency'] !== 'INR' || (int)($record['amount_refunded'] ?? 0) !== 0) throw new RuntimeException('Payment has not been captured for this plan.');
    return ['order'=>$order,'payment'=>$payment,'amount'=>$amount,'cycle'=>$pending['cycle']];
}
