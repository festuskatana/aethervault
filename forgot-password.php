<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=no">
    <title>Aether Vault - Forgot Password</title>
    <link rel="stylesheet" href="css/style.css">
    <link rel="stylesheet" href="css/auth.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>
    <div class="auth-container auth-reset-shell">
        <div class="auth-card auth-reset-card">
            <div class="auth-header">
                <div class="logo app-brand">
                    <div class="app-logo-wrap" data-app-logo-wrap>
                        <img class="app-logo-image" data-app-logo alt="App logo">
                        <i class="fas fa-shield-alt app-logo-fallback" data-app-logo-fallback></i>
                    </div>
                    <span data-app-name>Aether Vault</span>
                </div>
                <h2>Forgot Password</h2>
                <p>Recover your account with an email OTP and set a new password.</p>
            </div>

            <div class="otp-steps auth-inline-step" id="otpStepIndicator">
                <div class="otp-step active" id="otpStepRequest">
                    <span class="otp-step-number">1</span>
                    <div>
                        <strong>Enter email</strong>
                        <p>Send OTP</p>
                    </div>
                </div>
            </div>

            <form id="forgotPasswordForm" class="auth-form otp-form">
                <section class="otp-panel active" id="otpPanelRequest">
                    <div class="otp-panel-head">
                        <h3>Email verification</h3>
                        <p>Enter the email linked to your account and request a one-time passcode.</p>
                    </div>
                    <div class="input-group">
                        <i class="fas fa-envelope"></i>
                        <input type="email" id="resetEmail" placeholder="Email address" required>
                    </div>
                    <button type="button" class="auth-btn" id="sendOtpBtn">
                        <span>Send OTP</span>
                        <i class="fas fa-paper-plane"></i>
                    </button>
                </section>

                <section class="otp-panel" id="otpPanelVerify" hidden>
                    <div class="otp-panel-head">
                        <h3>Check your inbox</h3>
                        <p>Enter the 6-digit OTP sent to your email address.</p>
                    </div>
                    <div class="otp-boxes-wrap">
                        <div class="otp-boxes" id="otpBoxes">
                            <input type="text" class="otp-box" inputmode="numeric" maxlength="1" data-otp-index="0" autocomplete="one-time-code">
                            <input type="text" class="otp-box" inputmode="numeric" maxlength="1" data-otp-index="1">
                            <input type="text" class="otp-box" inputmode="numeric" maxlength="1" data-otp-index="2">
                            <input type="text" class="otp-box" inputmode="numeric" maxlength="1" data-otp-index="3">
                            <input type="text" class="otp-box" inputmode="numeric" maxlength="1" data-otp-index="4">
                            <input type="text" class="otp-box" inputmode="numeric" maxlength="1" data-otp-index="5">
                        </div>
                        <input type="hidden" id="resetOtp">
                    </div>
                    <div class="forgot-actions">
                        <button type="button" class="secondary-btn" id="backToRequestBtn">Back</button>
                        <button type="button" class="auth-btn" id="verifyOtpBtn">
                            <span>Verify OTP</span>
                            <i class="fas fa-badge-check"></i>
                        </button>
                    </div>
                    <button type="button" class="text-btn otp-resend-btn" id="resendOtpBtn" disabled>Resend OTP in 30s</button>
                </section>

                <section class="otp-panel" id="otpPanelReset" hidden>
                    <div class="otp-panel-head">
                        <h3>Create a new password</h3>
                        <p>Set a strong new password after your OTP has been verified.</p>
                    </div>
                    <div class="input-group">
                        <i class="fas fa-lock"></i>
                        <input type="password" id="resetNewPassword" placeholder="New password">
                    </div>
                    <div class="input-group">
                        <i class="fas fa-check-circle"></i>
                        <input type="password" id="resetConfirmPassword" placeholder="Confirm new password">
                    </div>
                    <div class="forgot-actions">
                        <button type="button" class="secondary-btn" id="backToVerifyBtn">Back</button>
                        <button type="submit" class="auth-btn">
                            <span>Reset Password</span>
                            <i class="fas fa-unlock-keyhole"></i>
                        </button>
                    </div>
                </section>
            </form>

            <div class="auth-footer">
                <p>Remember your password? <a href="index.php">Back to sign in</a></p>
            </div>
        </div>
    </div>

    <div id="toast" class="toast"></div>

    <script src="js/utils.js"></script>
    <script src="js/api.js"></script>
    <script src="js/app-settings.js"></script>
    <script src="js/forgot-password.js"></script>
</body>
</html>
