<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=no">
    <title>Aether Vault - Secure Private Space</title>
    <link rel="stylesheet" href="css/style.css">
    <link rel="stylesheet" href="css/auth.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>
    <div class="auth-container">
        <div class="auth-card">
            <div class="auth-header">
                <div class="logo app-brand">
                    <div class="app-logo-wrap" data-app-logo-wrap>
                        <img class="app-logo-image" data-app-logo alt="App logo">
                        <i class="fas fa-shield-alt app-logo-fallback" data-app-logo-fallback></i>
                    </div>
                    <span data-app-name>Aether Vault</span>
                </div>
                <h2>Welcome Back</h2>
                <p>Sign in to access your secure vault</p>
            </div>
            
            <form id="loginForm" class="auth-form">
                <div class="input-group">
                    <i class="fas fa-user"></i>
                    <input type="text" id="username" placeholder="Username or Email" required>
                </div>
                
                <div class="input-group">
                    <i class="fas fa-lock"></i>
                    <input type="password" id="password" placeholder="Password" required>
                    <i class="fas fa-eye-slash toggle-password"></i>
                </div>
                
                <div class="form-options">
                    <label class="checkbox-label">
                        <input type="checkbox" id="rememberMe">
                        <span>Remember me</span>
                    </label>
                    <a href="forgot-password.php" class="forgot-link">Forgot Password?</a>
                </div>
                
                <button type="submit" class="auth-btn">
                    <span>Sign In</span>
                    <i class="fas fa-arrow-right"></i>
                </button>
            </form>
            
            <div class="auth-footer">
                <p>Don't have an account? <a href="#" id="showRegister">Create Account</a></p>
            </div>
        </div>
        
        <div class="auth-card register-card" style="display: none;">
            <div class="auth-header">
                <div class="logo app-brand">
                    <div class="app-logo-wrap" data-app-logo-wrap>
                        <img class="app-logo-image" data-app-logo alt="App logo">
                        <i class="fas fa-shield-alt app-logo-fallback" data-app-logo-fallback></i>
                    </div>
                    <span data-app-name>Aether Vault</span>
                </div>
                <h2>Create Account</h2>
                <p>Join the secure communication network</p>
            </div>
            
            <form id="registerForm" class="auth-form">
                <div class="input-group">
                    <i class="fas fa-user"></i>
                    <input type="text" id="regUsername" placeholder="Username" required>
                </div>
                
                <div class="input-group">
                    <i class="fas fa-envelope"></i>
                    <input type="email" id="regEmail" placeholder="Email" required>
                </div>
                
                <div class="input-group">
                    <i class="fas fa-lock"></i>
                    <input type="password" id="regPassword" placeholder="Password" required>
                </div>
                
                <div class="input-group">
                    <i class="fas fa-check-circle"></i>
                    <input type="password" id="confirmPassword" placeholder="Confirm Password" required>
                </div>
                
                <div class="terms">
                    <label class="checkbox-label">
                        <input type="checkbox" id="acceptTerms" required>
                        <span>I agree to the <a href="#">Terms of Service</a> and <a href="#">Privacy Policy</a></span>
                    </label>
                </div>
                
                <button type="submit" class="auth-btn">
                    <span>Create Account</span>
                    <i class="fas fa-user-plus"></i>
                </button>
            </form>
            
            <div class="auth-footer">
                <p>Already have an account? <a href="#" id="showLogin">Sign In</a></p>
            </div>
        </div>
    </div>
    
    <div id="toast" class="toast"></div>
    
    <script src="js/utils.js"></script>
    <script src="js/api.js"></script>
    <script src="js/app-settings.js"></script>
    <script src="js/auth.js"></script>
</body>
</html>
