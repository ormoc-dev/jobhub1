/**
 * Employer AI chat — toggle, send, typing indicator.
 * Expects #aiChatWidget (floating FAB) markup from includes/sidebar.php.
 */
(function () {
    const chatToggle = document.getElementById('aiChatToggle');
    const chatWindow = document.getElementById('aiChatWindow');
    const chatClose = document.getElementById('aiChatClose');
    const chatInput = document.getElementById('aiChatInput');
    const chatSend = document.getElementById('aiChatSend');
    const chatMessages = document.getElementById('aiChatMessages');

    if (!chatToggle || !chatWindow || !chatClose || !chatInput || !chatSend || !chatMessages) {
        return;
    }

    let isOpen = false;

    chatToggle.addEventListener('click', () => {
        isOpen = !isOpen;
        chatWindow.style.display = isOpen ? 'flex' : 'none';
        chatToggle.classList.toggle('is-open', isOpen);
        chatToggle.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
        if (isOpen) {
            chatInput.focus();
        }
    });

    chatClose.addEventListener('click', () => {
        isOpen = false;
        chatWindow.style.display = 'none';
        chatToggle.classList.remove('is-open');
        chatToggle.setAttribute('aria-expanded', 'false');
    });

    async function sendMessage(message) {
        if (!message.trim()) return;

        addMessage(message, 'user');
        chatInput.value = '';
        chatInput.style.height = 'auto';

        showTyping();

        try {
            const response = await fetch('ai_chat.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    message: message,
                    context: 'employer_dashboard',
                }),
            });

            const data = await response.json();

            hideTyping();

            if (data.success) {
                addMessage(data.response, 'bot');
            } else {
                console.error('AI Chat Error:', data);
                addMessage('Sorry, I encountered an error: ' + (data.error || 'Unknown error'), 'bot');
            }
        } catch (error) {
            hideTyping();
            console.error('AI Chat Exception:', error);
            addMessage("Sorry, I'm having trouble connecting. Please try again later.", 'bot');
        }
    }

    function addMessage(text, sender) {
        const messageDiv = document.createElement('div');
        messageDiv.className = `ai-message ai-message-${sender}`;
        messageDiv.innerHTML = `
                    <div class="ai-message-avatar">
                        <i class="fas fa-${sender === 'bot' ? 'robot' : 'user'}"></i>
                    </div>
                    <div class="ai-message-content">
                        <p>${escapeHtml(text)}</p>
                    </div>
                `;
        chatMessages.appendChild(messageDiv);
        chatMessages.scrollTop = chatMessages.scrollHeight;
    }

    function showTyping() {
        const typingDiv = document.createElement('div');
        typingDiv.id = 'aiTyping';
        typingDiv.className = 'ai-message ai-message-bot';
        typingDiv.innerHTML = `
                    <div class="ai-message-avatar">
                        <i class="fas fa-robot"></i>
                    </div>
                    <div class="ai-message-content ai-typing">
                        <div class="ai-typing-dot"></div>
                        <div class="ai-typing-dot"></div>
                        <div class="ai-typing-dot"></div>
                    </div>
                `;
        chatMessages.appendChild(typingDiv);
        chatMessages.scrollTop = chatMessages.scrollHeight;
    }

    function hideTyping() {
        const typing = document.getElementById('aiTyping');
        if (typing) typing.remove();
    }

    function escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

    chatSend.addEventListener('click', () => {
        sendMessage(chatInput.value);
    });

    chatInput.addEventListener('keypress', (e) => {
        if (e.key === 'Enter' && !e.shiftKey) {
            e.preventDefault();
            sendMessage(chatInput.value);
        }
    });

    chatInput.addEventListener('input', function () {
        this.style.height = 'auto';
        this.style.height = Math.min(this.scrollHeight, 100) + 'px';
    });
})();
