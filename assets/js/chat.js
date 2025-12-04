document.addEventListener('DOMContentLoaded', function () {
    const chatList = document.getElementById('ubc-chat-list');
    const messagesContainer = document.getElementById('ubc-chat-messages');
    const input = document.getElementById('ubc-chat-input');
    const sendBtn = document.getElementById('ubc-chat-send-btn');
    const newChatBtn = document.getElementById('ubc-chat-new-btn');

    let currentChatId = null;
    let chats = {};

    /**
     * Initialize the chat interface.
     * Loads chat history on page load.
     */
    loadHistory();

    // Event Listeners
    sendBtn.addEventListener('click', sendMessage);
    input.addEventListener('keypress', function (e) {
        if (e.key === 'Enter' && !e.shiftKey) {
            e.preventDefault();
            sendMessage();
        }
    });
    newChatBtn.addEventListener('click', createNewChat);

    /**
     * Loads chat history from the server.
     * Fetches all stored chats for the current user and renders the list.
     * Selects the most recent chat or creates a new one if none exist.
     */
    function loadHistory() {
        fetch(ubcSimpleChat.ajaxUrl + '?action=ubc_chat_get_history&nonce=' + ubcSimpleChat.nonce)
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    chats = data.data;
                    renderChatList();
                    // Select most recent chat or create new
                    const chatIds = Object.keys(chats).sort((a, b) => chats[b].created_at - chats[a].created_at);
                    if (chatIds.length > 0) {
                        selectChat(chatIds[0]);
                    } else {
                        createNewChat();
                    }
                }
            });
    }

    /**
     * Renders the list of chats in the sidebar.
     * Sorts chats by creation time (newest first).
     * Adds click listeners for selection and deletion.
     */
    function renderChatList() {
        chatList.innerHTML = '';
        const sortedChatIds = Object.keys(chats).sort((a, b) => chats[b].created_at - chats[a].created_at);

        sortedChatIds.forEach(chatId => {
            const chat = chats[chatId];
            const li = document.createElement('li');
            li.dataset.id = chatId;
            if (chatId === currentChatId) {
                li.classList.add('active');
            }

            const titleSpan = document.createElement('span');
            titleSpan.className = 'chat-title';
            titleSpan.textContent = chat.title || 'New Chat';

            const deleteBtn = document.createElement('button');
            deleteBtn.innerHTML = '&times;';
            deleteBtn.className = 'delete-chat-btn';
            deleteBtn.title = 'Delete Chat';
            deleteBtn.onclick = (e) => {
                e.stopPropagation();
                deleteChat(chatId);
            };

            li.appendChild(titleSpan);
            li.appendChild(deleteBtn);
            li.addEventListener('click', () => selectChat(chatId));
            chatList.appendChild(li);
        });
    }

    /**
     * Selects a chat and renders its messages.
     *
     * @param {string} chatId The ID of the chat to select.
     */
    function selectChat(chatId) {
        currentChatId = chatId;
        renderChatList();
        renderMessages(chats[chatId].messages || []);
    }

    /**
     * Creates a new chat session.
     * Sends a request to the server to generate a new chat ID.
     */
    function createNewChat() {
        const formData = new FormData();
        formData.append('action', 'ubc_chat_new_chat');
        formData.append('nonce', ubcSimpleChat.nonce);

        fetch(ubcSimpleChat.ajaxUrl, {
            method: 'POST',
            body: formData
        })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    const newChatId = data.data.chat_id;
                    chats[newChatId] = {
                        id: newChatId,
                        title: 'New Chat',
                        messages: [],
                        created_at: Date.now() / 1000
                    };
                    selectChat(newChatId);
                }
            });
    }

    /**
     * Deletes a chat session.
     * Confirms with the user before deletion.
     *
     * @param {string} chatId The ID of the chat to delete.
     */
    function deleteChat(chatId) {
        if (!confirm('Are you sure you want to delete this chat?')) return;

        const formData = new FormData();
        formData.append('action', 'ubc_chat_delete_chat');
        formData.append('chat_id', chatId);
        formData.append('nonce', ubcSimpleChat.nonce);

        fetch(ubcSimpleChat.ajaxUrl, {
            method: 'POST',
            body: formData
        })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    delete chats[chatId];
                    if (currentChatId === chatId) {
                        currentChatId = null;
                        messagesContainer.innerHTML = '';
                        // Select another chat if available
                        const chatIds = Object.keys(chats);
                        if (chatIds.length > 0) {
                            selectChat(chatIds[0]);
                        } else {
                            createNewChat();
                        }
                    } else {
                        renderChatList();
                    }
                }
            });
    }

    /**
     * Sends a message to the chat API.
     * Handles UI updates (optimistic append), typing indicator, and response handling.
     */
    function sendMessage() {
        const message = input.value.trim();
        if (!message || !currentChatId) return;

        // Add user message to UI immediately
        const userMsgObj = { role: 'user', content: message };
        chats[currentChatId].messages.push(userMsgObj);
        appendMessage(userMsgObj);

        // Update title if first message
        if (chats[currentChatId].messages.length === 1) {
            chats[currentChatId].title = message.substring(0, 30) + (message.length > 30 ? '...' : '');
            renderChatList();
        }

        input.value = '';
        input.disabled = true;
        sendBtn.disabled = true;

        // Show typing indicator
        const typingIndicator = showTypingIndicator();
        scrollToBottom();

        const formData = new FormData();
        formData.append('action', 'ubc_chat_send_message');
        formData.append('message', message);
        formData.append('chat_id', currentChatId);
        formData.append('nonce', ubcSimpleChat.nonce);

        fetch(ubcSimpleChat.ajaxUrl, {
            method: 'POST',
            body: formData
        })
            .then(response => response.json())
            .then(data => {
                // Remove typing indicator
                typingIndicator.remove();
                input.disabled = false;
                sendBtn.disabled = false;
                input.focus();

                if (data.success) {
                    const aiMsgObj = data.data;
                    chats[currentChatId].messages.push(aiMsgObj);
                    appendMessage(aiMsgObj);
                } else {
                    alert('Error: ' + data.data);
                }
            })
            .catch(err => {
                typingIndicator.remove();
                input.disabled = false;
                sendBtn.disabled = false;
                alert('Network Error');
            });
    }

    /**
     * Renders all messages for the current chat.
     *
     * @param {Array} messages List of message objects.
     */
    function renderMessages(messages) {
        messagesContainer.innerHTML = '';
        messages.forEach(msg => appendMessage(msg));
        scrollToBottom();
    }

    /**
     * Appends a single message to the chat window.
     * Handles formatting of newlines and displaying sources for assistant messages.
     *
     * @param {Object} msg The message object {role, content, sources?}.
     */
    function appendMessage(msg) {
        const div = document.createElement('div');
        div.className = `chat-message ${msg.role}`;

        // Convert newlines to <br> for simple formatting
        const content = msg.content.replace(/\n/g, '<br>');
        div.innerHTML = content;

        if (msg.role === 'assistant' && msg.sources && msg.sources.length > 0) {
            const sourcesDiv = document.createElement('div');
            sourcesDiv.className = 'chat-sources';
            sourcesDiv.innerHTML = '<strong>Sources:</strong><ul>' +
                msg.sources.map(s => `<li><a href="${s.url}" target="_blank">${s.title || s.url}</a></li>`).join('') +
                '</ul>';
            div.appendChild(sourcesDiv);
        } else if (msg.role === 'assistant') {
            const sourcesDiv = document.createElement('div');
            sourcesDiv.className = 'chat-sources';
            sourcesDiv.innerHTML = '<em>No relevant sources found in knowledge base.</em>';
            div.appendChild(sourcesDiv);
        }

        messagesContainer.appendChild(div);
        scrollToBottom();
    }

    function showTypingIndicator() {
        const div = document.createElement('div');
        div.className = 'typing-indicator';
        div.innerHTML = '<div class="typing-dot"></div><div class="typing-dot"></div><div class="typing-dot"></div>';
        messagesContainer.appendChild(div);
        return div;
    }

    function scrollToBottom() {
        messagesContainer.scrollTop = messagesContainer.scrollHeight;
    }
});
