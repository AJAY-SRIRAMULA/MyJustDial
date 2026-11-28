<?php
require __DIR__ . '/../includes/bootstrap.php';
require __DIR__ . '/../includes/razorpay_test.php';
header('Content-Type: application/json');
try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !hash_equals(csrfToken(), (string)($_POST['csrf_token'] ?? ''))) throw new RuntimeException('Invalid request.');
    $email = strtolower(trim((string)($_POST['email'] ?? '')));
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) throw new RuntimeException('Invalid email.');
    if (!tableExists($pdo, 'membership_test_payments')) throw new RuntimeException('Payment setup is incomplete.');
    $stmt = $pdo->prepare("SELECT subscription_id, monthly_amount, yearly_amount FROM subscription_types WHERE subscription_id = ? AND profile_type = 'paid' AND subscription_type = 'member' AND sub_active = 1");
    $stmt->execute([(int)($_POST['plan_id'] ?? 0)]);
    $plan = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($plan) {
        require_once __DIR__ . '/../includes/membership_plans.php';
        if ((mpMeta($pdo, (int)$plan['subscription_id'])['disallow_signups'] ?? '0') === '1') throw new RuntimeException('Public signup is disabled.');
    }
    if (!$plan) throw new RuntimeException('Invalid paid plan.');
    $check = $pdo->prepare('SELECT user_id FROM users_data WHERE email = ?');
    $check->execute([$email]);
    if ($check->fetchColumn()) throw new RuntimeException('Email already registered.');
    $cycle = (string)($_POST['billing_cycle'] ?? '');
    $amount = razorpayPlanAmount($plan, $cycle);
    $key = razorpayTestKey();
    $order = razorpayTestRequest('orders', ['amount'=>$amount,'currency'=>'INR','receipt'=>'mjd_' . bin2hex(random_bytes(12))]);
    $_SESSION['razorpay_signup_orders'][$order['id']] = ['plan'=>(int)$plan['subscription_id'],'email'=>$email,'cycle'=>$cycle];
    echo json_encode(['ok'=>true,'key'=>$key,'order_id'=>$order['id'],'amount'=>$amount]);
} catch (Throwable $e) {
    error_log('Razorpay order: ' . $e->getMessage());
    http_response_code(400);
    echo json_encode(['ok'=>false,'message'=>$e instanceof RuntimeException && !($e instanceof PDOException) ? $e->getMessage() : 'Order setup failed. Check the Apache error log.']);
}
