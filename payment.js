async function verifyPayment(paymentResponse) {
  try {
    const response = await fetch("/verify-payment.php", {
      method: "POST",
      headers: {
        "Content-Type": "application/json",
      },
      body: JSON.stringify({
        razorpay_payment_id: paymentResponse.razorpay_payment_id,
        razorpay_order_id: paymentResponse.razorpay_order_id,
        razorpay_signature: paymentResponse.razorpay_signature,
      }),
    });

    if (!response.ok) {
      throw new Error(`HTTP error! status: ${response.status}`);
    }

    const data = await response.json();

    if (data.success) {
      // Payment verified successfully
      Swal.fire({
        icon: "success",
        title: "Payment Successful!",
        text: "Your payment has been verified successfully.",
      }).then(() => {
        window.location.href = "booking-confirmation.php";
      });
    } else {
      // Payment verification failed with a known error
      Swal.fire({
        icon: "error",
        title: "Payment Verification Failed",
        text:
          data.message || "Unable to verify payment. Please contact support.",
      });
    }
  } catch (error) {
    // Network or other errors
    console.error("Payment verification error:", error);
    Swal.fire({
      icon: "error",
      title: "Payment Verification Failed",
      text: "Unable to verify payment due to network issues. Please check your internet connection and contact support if the problem persists.",
      footer: '<a href="contact-support.php">Contact Support</a>',
    });
  }
}
