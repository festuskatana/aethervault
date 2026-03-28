class WebSocketManager {
    constructor() {
        this.socket = null;
        this.reconnectAttempts = 0;
        this.maxReconnectAttempts = 5;
        this.reconnectDelay = 3000;
        this.messageHandlers = [];
    }
    
    connect() {
        const token = getAuthToken();
        if (!token) return;
        
        const wsUrl = `wss://${window.location.host}/ws?token=${token}`;
        this.socket = new WebSocket(wsUrl);
        
        this.socket.onopen = () => {
            console.log('WebSocket connected');
            this.reconnectAttempts = 0;
        };
        
        this.socket.onmessage = (event) => {
            const data = JSON.parse(event.data);
            this.handleMessage(data);
        };
        
        this.socket.onclose = () => {
            console.log('WebSocket disconnected');
            this.reconnect();
        };
        
        this.socket.onerror = (error) => {
            console.error('WebSocket error:', error);
        };
    }
    
    reconnect() {
        if (this.reconnectAttempts < this.maxReconnectAttempts) {
            setTimeout(() => {
                this.reconnectAttempts++;
                this.connect();
            }, this.reconnectDelay);
        }
    }
    
    handleMessage(data) {
        this.messageHandlers.forEach(handler => handler(data));
    }
    
    onMessage(handler) {
        this.messageHandlers.push(handler);
    }
    
    send(data) {
        if (this.socket && this.socket.readyState === WebSocket.OPEN) {
            this.socket.send(JSON.stringify(data));
        }
    }
    
    disconnect() {
        if (this.socket) {
            this.socket.close();
        }
    }
}

// Usage in chat.js
const wsManager = new WebSocketManager();

wsManager.onMessage((data) => {
    if (data.type === 'new_message' && currentChatUser && data.message.sender_id === currentChatUser.id) {
        loadMessages(currentChatUser.id);
    }
});