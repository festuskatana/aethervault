document.addEventListener('DOMContentLoaded', () => {
    // Check if already logged in
    const currentPage = window.location.pathname.split('/').pop();
    if (isAuthenticated() && (currentPage === '' || currentPage === 'index.php')) {
        window.location.href = 'dashboard.php';
    }
    
    // Toggle between login and register
    const showRegister = document.getElementById('showRegister');
    const showLogin = document.getElementById('showLogin');
    const loginCard = document.querySelector('.auth-card:first-child');
    const registerCard = document.querySelector('.register-card');
    
    if (showRegister) {
        showRegister.addEventListener('click', (e) => {
            e.preventDefault();
            loginCard.style.display = 'none';
            registerCard.style.display = 'block';
        });
    }
    
    if (showLogin) {
        showLogin.addEventListener('click', (e) => {
            e.preventDefault();
            registerCard.style.display = 'none';
            loginCard.style.display = 'block';
        });
    }
    
    // Toggle password visibility
    document.querySelectorAll('.toggle-password').forEach(toggle => {
        toggle.addEventListener('click', function() {
            const input = this.previousElementSibling;
            const type = input.getAttribute('type') === 'password' ? 'text' : 'password';
            input.setAttribute('type', type);
            this.classList.toggle('fa-eye');
            this.classList.toggle('fa-eye-slash');
        });
    });
    
    // Login Form Submit
    const loginForm = document.getElementById('loginForm');
    if (loginForm) {
        loginForm.addEventListener('submit', async (e) => {
            e.preventDefault();
            
            const username = document.getElementById('username').value;
            const password = document.getElementById('password').value;
            const rememberMe = document.getElementById('rememberMe')?.checked || false;
            
            const submitBtn = loginForm.querySelector('button');
            const originalText = submitBtn.innerHTML;
            submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Signing In...';
            submitBtn.disabled = true;
            
            try {
                const response = await API.login(username, password);
                
                if (response.success) {
                    setAuthToken(response.token);
                    setCurrentUser(response.user);
                    
                    showToast(response.user?.email_verified ? 'Login successful!' : 'Login successful! Verify your email from your profile.', 'success');
                    
                    setTimeout(() => {
                        window.location.href = 'dashboard.php';
                    }, 1000);
                } else {
                    showToast(response.error || 'Login failed', 'error');
                }
            } catch (error) {
                showToast(error.message, 'error');
            } finally {
                submitBtn.innerHTML = originalText;
                submitBtn.disabled = false;
            }
        });
    }
    
    // Register Form Submit
    const registerForm = document.getElementById('registerForm');
    if (registerForm) {
        registerForm.addEventListener('submit', async (e) => {
            e.preventDefault();
            
            const username = document.getElementById('regUsername').value;
            const email = document.getElementById('regEmail').value;
            const password = document.getElementById('regPassword').value;
            const confirmPassword = document.getElementById('confirmPassword').value;
            
            if (password !== confirmPassword) {
                showToast('Passwords do not match', 'error');
                return;
            }
            
            if (password.length < 6) {
                showToast('Password must be at least 6 characters', 'error');
                return;
            }
            
            const submitBtn = registerForm.querySelector('button');
            const originalText = submitBtn.innerHTML;
            submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Creating Account...';
            submitBtn.disabled = true;
            
            try {
                const response = await API.register(username, email, password);
                
                if (response.success) {
                    showToast('Account created successfully! Please login.', 'success');
                    
                    setTimeout(() => {
                        registerCard.style.display = 'none';
                        loginCard.style.display = 'block';
                        registerForm.reset();
                    }, 1500);
                } else {
                    showToast(response.error || 'Registration failed', 'error');
                }
            } catch (error) {
                showToast(error.message, 'error');
            } finally {
                submitBtn.innerHTML = originalText;
                submitBtn.disabled = false;
            }
        });
    }
    
    // Biometric Authentication (WebAuthn)
    if (document.getElementById('biometricUnlock')) {
        document.getElementById('biometricUnlock').addEventListener('click', async () => {
            if (!window.PublicKeyCredential) {
                showToast('Biometric authentication not supported on this device', 'error');
                return;
            }
            
            try {
                const publicKeyCredentialCreationOptions = {
                    challenge: new Uint8Array(32),
                    rp: { name: "Aether Vault" },
                    user: {
                        id: new Uint8Array(16),
                        name: getCurrentUser().username,
                        displayName: getCurrentUser().username
                    },
                    pubKeyCredParams: [{ alg: -7, type: "public-key" }]
                };
                
                const credential = await navigator.credentials.create({
                    publicKey: publicKeyCredentialCreationOptions
                });
                
                showToast('Biometric authentication configured!', 'success');
            } catch (error) {
                showToast('Biometric authentication failed', 'error');
            }
        });
    }
    
    // Logout
    if (document.getElementById('logoutBtn')) {
        document.getElementById('logoutBtn').addEventListener('click', () => {
            clearAuthData();
            showToast('Logged out successfully', 'success');
            setTimeout(() => {
                window.location.href = 'index.php';
            }, 1000);
        });
    }
});
