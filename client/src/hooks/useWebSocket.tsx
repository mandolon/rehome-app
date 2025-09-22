import { useEffect, useRef, useState, useCallback } from 'react';

interface WebSocketMessage {
  event: string;
  data: any;
}

export function useWebSocket(url: string, onMessage?: (message: WebSocketMessage) => void) {
  const [isConnected, setIsConnected] = useState(false);
  const ws = useRef<WebSocket | null>(null);
  const reconnectTimeoutRef = useRef<NodeJS.Timeout>();
  const onMessageRef = useRef(onMessage);

  // Keep the onMessage reference up to date
  useEffect(() => {
    onMessageRef.current = onMessage;
  });

  useEffect(() => {
    function connect() {
      try {
        ws.current = new WebSocket(url);
        
        ws.current.onopen = () => {
          console.log('WebSocket connected');
          setIsConnected(true);
        };
        
        ws.current.onmessage = (event) => {
          try {
            const message: WebSocketMessage = JSON.parse(event.data);
            onMessageRef.current?.(message);
          } catch (error) {
            console.error('Failed to parse WebSocket message:', error);
          }
        };
        
        ws.current.onclose = () => {
          console.log('WebSocket disconnected');
          setIsConnected(false);
          
          // Reconnect after 3 seconds
          reconnectTimeoutRef.current = setTimeout(() => {
            console.log('Attempting to reconnect WebSocket...');
            connect();
          }, 3000);
        };
        
        ws.current.onerror = (error) => {
          console.error('WebSocket error:', error);
        };
      } catch (error) {
        console.error('Failed to create WebSocket connection:', error);
      }
    }

    connect();

    return () => {
      if (reconnectTimeoutRef.current) {
        clearTimeout(reconnectTimeoutRef.current);
      }
      if (ws.current) {
        ws.current.close();
      }
    };
  }, [url]); // Removed onMessage from dependencies

  const sendMessage = useCallback((message: any) => {
    if (ws.current && ws.current.readyState === WebSocket.OPEN) {
      ws.current.send(JSON.stringify(message));
    }
  }, []);

  return { isConnected, sendMessage };
}