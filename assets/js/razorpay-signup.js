(function () {
    const form = document.getElementById('membership-signup-form');
    if (!form) return;
    const status = document.getElementById('payment-status');
    let busy = false;
    form.addEventListener('submit', async function (event) {
        if (event.defaultPrevented) return;
        if (form.elements.razorpay_payment_id.value) return;
        event.preventDefault();
        if (busy || !form.reportValidity()) return;
        if (form.elements.email.value.trim() !== form.elements.email_confirm.value.trim()) { status.textContent = 'Email addresses do not match.'; return; }
        if (form.elements.password.value !== form.elements.password_confirm.value) { status.textContent = 'Passwords do not match.'; return; }
        busy = true;
        try {
            const data = new FormData();
            ['csrf_token','plan_id','email','billing_cycle'].forEach(name => data.set(name, form.elements[name].value));
            const response = await fetch('razorpay-order.php', {method:'POST',body:data,credentials:'same-origin'});
            const order = await response.json();
            if (!response.ok || !order.ok) throw new Error(order.message || 'Order creation failed.');
            const checkout = new Razorpay({key:order.key, amount:order.amount, currency:'INR', order_id:order.order_id, name:'MyJustDial Directory', description:'Test membership payment', prefill:{email:form.elements.email.value}, handler:function(result){
                ['razorpay_order_id','razorpay_payment_id','razorpay_signature'].forEach(name => form.elements[name].value = result[name]);
                status.textContent = 'Payment received. Verifying and saving membership…';
                busy = false;
                form.requestSubmit();
            }, modal:{ondismiss:function(){busy=false;status.textContent='Payment cancelled. No membership created.';}}});
            checkout.on('payment.failed', function(){busy=false;status.textContent='Test payment failed. Please retry.';});
            checkout.open();
        } catch (error) {busy=false;status.textContent=error.message;}
    });
})();
