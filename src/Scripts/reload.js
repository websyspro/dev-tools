(() => {
    const WS_URL = 'ws://localhost:3000';

    let socket;
    let reconnectDelay = 1000;

    function log(msg, type = 'log') {
        console[type](`[DevReload] ${msg}`);
    }

    function connect() {
        log('Connecting to WebSocket...');

        socket = new WebSocket(WS_URL);

        socket.addEventListener('open', () => {
            log('Connected');
            reconnectDelay = 1000;
        });

        socket.addEventListener('message', (event) => {
            log(`Message received: ${event.data}`);

            if (event.data === 'reload') {
                log('Reloading page');
                window.location.reload();
            }
        });

        socket.addEventListener('close', () => {
            log('Disconnected. Reconnecting...');
            setTimeout(connect, reconnectDelay);
            reconnectDelay = Math.min(reconnectDelay * 2, 10000);
        });

        socket.addEventListener('error', (err) => {
            console.error('[DevReload] WebSocket error', err);
            socket.close();
        });
    }

    connect();
})();
