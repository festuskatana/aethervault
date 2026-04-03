const CURRENT_PATH = window.location.pathname;
const PROJECT_BASE_PATH = CURRENT_PATH.substring(0, CURRENT_PATH.lastIndexOf('/') + 1);
const API_BASE_URL = `${window.location.origin}${PROJECT_BASE_PATH}backend/api/`;

class API {
    static async request(endpoint, options = {}) {
        const token = getAuthToken();
        
        const headers = {
            'Content-Type': 'application/json',
            ...options.headers
        };
        
        if (token) {
            headers['Authorization'] = `Bearer ${token}`;
        }
        
        const config = {
            ...options,
            headers
        };
        
        try {
            const response = await fetch(`${API_BASE_URL}${endpoint}`, config);
            const rawResponse = await response.text();
            let data = {};
            
            try {
                data = rawResponse ? JSON.parse(rawResponse) : {};
            } catch (parseError) {
                throw new Error('The server returned an invalid response.');
            }
            
            if (response.status === 401) {
                clearAuthData();
            }

            if (!response.ok) {
                throw new Error(data.error || 'Something went wrong');
            }
            
            return data;
        } catch (error) {
            console.error('API Error:', error);
            throw error;
        }
    }
    
    // Auth Endpoints
    static async register(username, email, password) {
        return this.request('register.php', {
            method: 'POST',
            body: JSON.stringify({ username, email, password })
        });
    }
    
    static async login(username, password) {
        return this.request('login.php', {
            method: 'POST',
            body: JSON.stringify({ username, password })
        });
    }

    static async logout() {
        return this.request('logout.php', {
            method: 'POST'
        });
    }
    
    // Media Endpoints
    static async uploadMedia(file) {
        const formData = new FormData();
        formData.append('file', file);
        
        const token = getAuthToken();
        
        const response = await fetch(`${API_BASE_URL}upload_media.php`, {
            method: 'POST',
            headers: {
                'Authorization': `Bearer ${token}`
            },
            body: formData
        });

        const rawResponse = await response.text();
        let data = {};

        try {
            data = rawResponse ? JSON.parse(rawResponse) : {};
        } catch (parseError) {
            throw new Error('The upload endpoint returned an invalid response.');
        }

        if (!response.ok) {
            throw new Error(data.error || 'Upload failed');
        }
        return data;
    }
    
    static async getMedia(userId = null) {
        const query = userId ? `?user_id=${encodeURIComponent(userId)}` : '';
        return this.request(`fetch_media.php${query}`);
    }

    static async deleteMedia(mediaId) {
        return this.request('delete_media.php', {
            method: 'POST',
            body: JSON.stringify({ media_id: mediaId })
        });
    }
    
    // Chat Endpoints
    static async getMessages(userId, limit = 50, offset = 0) {
        return this.request(`fetch_messages.php?user_id=${userId}&limit=${limit}&offset=${offset}`);
    }
    
    static async sendMessage(receiverId, message) {
        return this.request('send_message.php', {
            method: 'POST',
            body: JSON.stringify({ receiver_id: receiverId, message })
        });
    }

    static async editMessage(messageId, message) {
        return this.request('edit_message.php', {
            method: 'POST',
            body: JSON.stringify({ message_id: messageId, message })
        });
    }

    static async deleteMessage(messageId) {
        return this.request('delete_message.php', {
            method: 'POST',
            body: JSON.stringify({ message_id: messageId })
        });
    }

    static async uploadChatMedia(receiverId, file, message = '') {
        const formData = new FormData();
        formData.append('receiver_id', receiverId);
        formData.append('message', message);
        formData.append('file', file);

        const response = await fetch(`${API_BASE_URL}upload_chat_media.php`, {
            method: 'POST',
            headers: {
                'Authorization': `Bearer ${getAuthToken()}`
            },
            body: formData
        });

        const rawResponse = await response.text();
        let data = {};

        try {
            data = rawResponse ? JSON.parse(rawResponse) : {};
        } catch (parseError) {
            throw new Error('The attachment upload endpoint returned an invalid response.');
        }

        if (!response.ok) {
            throw new Error(data.error || 'Attachment upload failed');
        }
        return data;
    }
    
    static async searchUsers(query) {
        return this.request(`search_users.php?q=${encodeURIComponent(query)}`);
    }
    
    static async getConversations() {
        return this.request('get_conversations.php');
    }

    static async getUnreadCount() {
        return this.request('unread_count.php');
    }

    static async getProfile(userId = null) {
        const query = userId ? `?user_id=${encodeURIComponent(userId)}` : '';
        return this.request(`get_profile.php${query}`);
    }

    static async getPublicSettings() {
        return this.request('get_public_settings.php', {
            headers: {}
        });
    }

    static async getAdminSettings() {
        return this.request('admin_get_settings.php');
    }

    static async getAdminDashboard() {
        return this.request('admin_dashboard.php');
    }

    static async updateAdminSettings(formData) {
        const response = await fetch(`${API_BASE_URL}admin_update_settings.php`, {
            method: 'POST',
            headers: {
                'Authorization': `Bearer ${getAuthToken()}`
            },
            body: formData
        });

        const rawResponse = await response.text();
        let data = {};
        try {
            data = rawResponse ? JSON.parse(rawResponse) : {};
        } catch (parseError) {
            throw new Error('The admin settings endpoint returned an invalid response.');
        }

        if (!response.ok) {
            throw new Error(data.error || 'Failed to update admin settings');
        }

        return data;
    }

    static async testAdminSmtp(email) {
        return this.request('admin_test_smtp.php', {
            method: 'POST',
            body: JSON.stringify({ email })
        });
    }

    static async requestPasswordOtp(email) {
        return this.request('forgot_password_request.php', {
            method: 'POST',
            body: JSON.stringify({ email })
        });
    }

    static async requestEmailVerification(email) {
        return this.request('request_email_verification.php', {
            method: 'POST',
            body: JSON.stringify({ email })
        });
    }

    static async verifyEmailVerification(email, otp) {
        return this.request('verify_email_verification.php', {
            method: 'POST',
            body: JSON.stringify({ email, otp })
        });
    }

    static async verifyPasswordOtp(email, otp) {
        return this.request('forgot_password_verify.php', {
            method: 'POST',
            body: JSON.stringify({ email, otp })
        });
    }

    static async resetPassword(email, otp, password) {
        return this.request('forgot_password_reset.php', {
            method: 'POST',
            body: JSON.stringify({ email, otp, password })
        });
    }
}

// Add these to your existing API object

API.sendFileMessage = async (userId, file, caption, replyToId) => {
    const formData = new FormData();
    formData.append('file', file);
    if (caption) formData.append('message', caption);
    if (replyToId) formData.append('reply_to', replyToId);
    
    const response = await fetch(`${API_BASE_URL}/messages/${userId}`, {
        method: 'POST',
        headers: {
            'Authorization': `Bearer ${getAuthToken()}`
        },
        body: formData
    });
    
    if (!response.ok) throw new Error('Failed to send file');
    return response.json();
};

API.sendMessage = async (userId, message, replyToId) => {
    const response = await fetch(`${API_BASE_URL}/messages/${userId}`, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'Authorization': `Bearer ${getAuthToken()}`
        },
        body: JSON.stringify({ message, reply_to: replyToId })
    });
    
    if (!response.ok) throw new Error('Failed to send message');
    return response.json();
};

API.editMessage = async (messageId, newMessage) => {
    const response = await fetch(`${API_BASE_URL}/messages/${messageId}`, {
        method: 'PUT',
        headers: {
            'Content-Type': 'application/json',
            'Authorization': `Bearer ${getAuthToken()}`
        },
        body: JSON.stringify({ message: newMessage })
    });
    
    if (!response.ok) throw new Error('Failed to edit message');
    return response.json();
};

API.deleteMessage = async (messageId) => {
    const response = await fetch(`${API_BASE_URL}/messages/${messageId}`, {
        method: 'DELETE',
        headers: {
            'Authorization': `Bearer ${getAuthToken()}`
        }
    });
    
    if (!response.ok) throw new Error('Failed to delete message');
    return response.json();
};
