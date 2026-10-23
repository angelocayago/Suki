export function initChatHistory(history) {
    if (!history || history.dataset.chatHistoryReady) return;
    history.dataset.chatHistoryReady = 'true';
    if (history.dataset.latestPage === 'true') history.scrollTop = history.scrollHeight;
}
if (typeof document !== 'undefined') {
    const init = () => document.querySelectorAll('[data-chat-history]').forEach(initChatHistory);
    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', init); else init();
}
