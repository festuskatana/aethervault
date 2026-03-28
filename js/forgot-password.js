let otpFlowStep = 1;
let otpVerified = false;
let otpSent = false;
let resendCountdown = 0;
let resendTimer = null;
let otpAutoVerifyInFlight = false;

document.addEventListener('DOMContentLoaded', () => {
    const sendOtpBtn = document.getElementById('sendOtpBtn');
    const verifyOtpBtn = document.getElementById('verifyOtpBtn');
    const backToRequestBtn = document.getElementById('backToRequestBtn');
    const backToVerifyBtn = document.getElementById('backToVerifyBtn');
    const resendOtpBtn = document.getElementById('resendOtpBtn');
    const form = document.getElementById('forgotPasswordForm');
    const otpBoxes = Array.from(document.querySelectorAll('.otp-box'));

    syncOtpStep();
    bindOtpBoxes(otpBoxes);

    if (sendOtpBtn) {
        sendOtpBtn.addEventListener('click', sendResetOtp);
    }

    if (verifyOtpBtn) {
        verifyOtpBtn.addEventListener('click', verifyResetOtp);
    }

    if (backToRequestBtn) {
        backToRequestBtn.addEventListener('click', () => {
            otpFlowStep = 1;
            syncOtpStep();
        });
    }

    if (backToVerifyBtn) {
        backToVerifyBtn.addEventListener('click', () => {
            otpFlowStep = 2;
            syncOtpStep();
        });
    }

    if (form) {
        form.addEventListener('submit', async (event) => {
            event.preventDefault();
            await resetPassword();
        });
    }

    if (resendOtpBtn) {
        resendOtpBtn.addEventListener('click', async () => {
            if (resendCountdown > 0) {
                return;
            }
            await sendResetOtp(true);
        });
    }
});

async function sendResetOtp(isResend = false) {
    const email = document.getElementById('resetEmail').value.trim();
    if (!email) {
        showToast('Enter your email first', 'error');
        return;
    }

    try {
        const response = await API.requestPasswordOtp(email);
        otpFlowStep = 2;
        otpVerified = false;
        otpSent = true;
        clearOtpBoxes();
        syncOtpStep();
        startResendCountdown();
        showToast(response.message || (isResend ? 'OTP resent' : 'OTP sent'), 'success');
    } catch (error) {
        otpSent = false;
        showToast(error.message || 'Failed to send OTP', 'error');
    }
}

async function verifyResetOtp() {
    if (otpAutoVerifyInFlight) {
        return;
    }

    const email = document.getElementById('resetEmail').value.trim();
    const otp = document.getElementById('resetOtp').value.trim();

    if (!otpSent) {
        showToast('Send the OTP first', 'error');
        return;
    }

    if (!email || !otp) {
        showToast('Enter email and OTP', 'error');
        return;
    }

    try {
        otpAutoVerifyInFlight = true;
        const response = await API.verifyPasswordOtp(email, otp);
        otpFlowStep = 3;
        otpVerified = true;
        syncOtpStep();
        showToast(response.message || 'OTP verified', 'success');
    } catch (error) {
        showToast(error.message || 'OTP verification failed', 'error');
    } finally {
        otpAutoVerifyInFlight = false;
    }
}

async function resetPassword() {
    const email = document.getElementById('resetEmail').value.trim();
    const otp = document.getElementById('resetOtp').value.trim();
    const password = document.getElementById('resetNewPassword').value;
    const confirmPassword = document.getElementById('resetConfirmPassword').value;

    if (!otpVerified) {
        showToast('Verify the OTP before resetting your password', 'error');
        return;
    }

    if (password !== confirmPassword) {
        showToast('Passwords do not match', 'error');
        return;
    }

    try {
        const response = await API.resetPassword(email, otp, password);
        showToast(response.message || 'Password reset successfully', 'success');
        setTimeout(() => {
            window.location.href = 'index.php';
        }, 1000);
    } catch (error) {
        showToast(error.message || 'Password reset failed', 'error');
    }
}

function syncOtpStep() {
    const stepIndicator = document.getElementById('otpStepIndicator');
    const stepMap = {
        1: { title: 'Enter email', subtitle: 'Send OTP' },
        2: { title: 'Enter OTP', subtitle: 'Verify code' },
        3: { title: 'New password', subtitle: 'Reset access' }
    };

    if (stepIndicator) {
        stepIndicator.innerHTML = `
            <div class="otp-step active" id="otpDynamicStep">
                <span class="otp-step-number">${otpFlowStep}</span>
                <div>
                    <strong>${stepMap[otpFlowStep].title}</strong>
                    <p>${stepMap[otpFlowStep].subtitle}</p>
                </div>
            </div>
        `;
    }

    [
        { id: 1, panel: 'otpPanelRequest' },
        { id: 2, panel: 'otpPanelVerify' },
        { id: 3, panel: 'otpPanelReset' }
    ].forEach(({ id, panel }) => {
        const panelElement = document.getElementById(panel);
        if (!panelElement) {
            return;
        }
        panelElement.classList.toggle('active', otpFlowStep === id);
        panelElement.hidden = otpFlowStep !== id;
    });

    const emailInput = document.getElementById('resetEmail');
    const otpInput = document.getElementById('resetOtp');
    const newPasswordInput = document.getElementById('resetNewPassword');
    const confirmPasswordInput = document.getElementById('resetConfirmPassword');
    const otpBoxes = Array.from(document.querySelectorAll('.otp-box'));

    if (emailInput) {
        emailInput.readOnly = otpFlowStep > 1;
    }

    if (otpInput) {
        otpInput.disabled = otpFlowStep < 2;
        otpInput.required = otpFlowStep === 2;
    }

    otpBoxes.forEach((box) => {
        box.disabled = otpFlowStep < 2;
    });

    if (otpFlowStep === 2) {
        otpBoxes[0]?.focus();
    }

    if (newPasswordInput) {
        newPasswordInput.disabled = otpFlowStep < 3;
        newPasswordInput.required = otpFlowStep === 3;
    }

    if (confirmPasswordInput) {
        confirmPasswordInput.disabled = otpFlowStep < 3;
        confirmPasswordInput.required = otpFlowStep === 3;
    }
}

function startResendCountdown() {
    const resendOtpBtn = document.getElementById('resendOtpBtn');
    resendCountdown = 30;
    updateResendButton();

    if (resendTimer) {
        clearInterval(resendTimer);
    }

    resendTimer = setInterval(() => {
        resendCountdown -= 1;
        updateResendButton();

        if (resendCountdown <= 0) {
            clearInterval(resendTimer);
            resendTimer = null;
        }
    }, 1000);
}

function updateResendButton() {
    const resendOtpBtn = document.getElementById('resendOtpBtn');
    if (!resendOtpBtn) {
        return;
    }

    if (resendCountdown > 0) {
        resendOtpBtn.disabled = true;
        resendOtpBtn.textContent = `Resend OTP in ${resendCountdown}s`;
    } else {
        resendOtpBtn.disabled = false;
        resendOtpBtn.textContent = 'Resend OTP';
    }
}

function bindOtpBoxes(otpBoxes) {
    if (!otpBoxes.length) {
        return;
    }

    otpBoxes.forEach((box, index) => {
        box.addEventListener('input', (event) => {
            const digits = String(event.target.value || '').replace(/\D/g, '');

            if (!digits) {
                event.target.value = '';
                updateHiddenOtp();
                return;
            }

            if (digits.length > 1) {
                fillOtpBoxes(digits);
                return;
            }

            event.target.value = digits;
            updateHiddenOtp();
            otpBoxes[index + 1]?.focus();
        });

        box.addEventListener('keydown', (event) => {
            if (event.key === 'Backspace' && !event.target.value && index > 0) {
                otpBoxes[index - 1].focus();
                otpBoxes[index - 1].value = '';
                updateHiddenOtp();
            }

            if (event.key === 'ArrowLeft' && index > 0) {
                event.preventDefault();
                otpBoxes[index - 1].focus();
            }

            if (event.key === 'ArrowRight' && index < otpBoxes.length - 1) {
                event.preventDefault();
                otpBoxes[index + 1].focus();
            }
        });

        box.addEventListener('paste', (event) => {
            event.preventDefault();
            const pasted = (event.clipboardData?.getData('text') || '').replace(/\D/g, '');
            fillOtpBoxes(pasted);
        });
    });
}

function fillOtpBoxes(value) {
    const otpBoxes = Array.from(document.querySelectorAll('.otp-box'));
    const digits = String(value || '').replace(/\D/g, '').slice(0, otpBoxes.length).split('');

    otpBoxes.forEach((box, index) => {
        box.value = digits[index] || '';
    });

    updateHiddenOtp();

    const nextEmpty = otpBoxes.find((box) => !box.value);
    if (nextEmpty) {
        nextEmpty.focus();
    } else {
        otpBoxes[otpBoxes.length - 1]?.focus();
    }
}

function updateHiddenOtp() {
    const hiddenOtp = document.getElementById('resetOtp');
    const otpBoxes = Array.from(document.querySelectorAll('.otp-box'));
    if (!hiddenOtp) {
        return;
    }

    hiddenOtp.value = otpBoxes.map((box) => box.value).join('');

    if (
        otpFlowStep === 2
        && otpSent
        && hiddenOtp.value.length === otpBoxes.length
        && otpBoxes.every((box) => box.value)
        && !otpAutoVerifyInFlight
    ) {
        window.setTimeout(() => {
            if (document.getElementById('resetOtp')?.value.length === otpBoxes.length && otpFlowStep === 2) {
                verifyResetOtp();
            }
        }, 120);
    }
}

function clearOtpBoxes() {
    const otpBoxes = Array.from(document.querySelectorAll('.otp-box'));
    otpBoxes.forEach((box) => {
        box.value = '';
    });
    updateHiddenOtp();
}
