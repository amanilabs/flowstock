import Echo from 'laravel-echo'
import Pusher from 'pusher-js'
import { API_URL, getToken } from '@/lib/api'

// laravel-echo's Reverb/Pusher broadcaster reads this off the global scope
// internally — required even though nothing here calls Pusher directly.
declare global {
  interface Window {
    Pusher: typeof Pusher
  }
}
window.Pusher = Pusher

let echo: Echo<'reverb'> | null = null

/**
 * Returns the shared Echo connection, creating it on first use. Channel
 * authorization goes through our own /broadcasting/auth (routes/api.php),
 * not Laravel's default session-based one, since this SPA authenticates
 * with a Sanctum bearer token, not cookies.
 */
export function connectEcho(): Echo<'reverb'> {
  if (echo) return echo

  echo = new Echo({
    broadcaster: 'reverb',
    key: import.meta.env.VITE_REVERB_APP_KEY,
    wsHost: import.meta.env.VITE_REVERB_HOST,
    wsPort: Number(import.meta.env.VITE_REVERB_PORT ?? 8080),
    wssPort: Number(import.meta.env.VITE_REVERB_PORT ?? 8080),
    forceTLS: (import.meta.env.VITE_REVERB_SCHEME ?? 'http') === 'https',
    enabledTransports: ['ws', 'wss'],
    authorizer: (channel: { name: string }) => ({
      authorize(
        socketId: string,
        callback: (error: Error | null, authData: { auth: string } | null) => void,
      ) {
        fetch(`${API_URL}/broadcasting/auth`, {
          method: 'POST',
          headers: {
            'Content-Type': 'application/json',
            Accept: 'application/json',
            Authorization: `Bearer ${getToken() ?? ''}`,
          },
          body: JSON.stringify({ socket_id: socketId, channel_name: channel.name }),
        })
          .then((response) => {
            if (!response.ok) throw new Error(`Broadcast auth failed (${response.status})`)
            return response.json()
          })
          .then((data) => callback(null, data))
          .catch((error: unknown) => {
            callback(error instanceof Error ? error : new Error(String(error)), null)
          })
      },
    }),
  })

  return echo
}

/** Tears down the connection entirely — call this on logout. */
export function disconnectEcho(): void {
  echo?.disconnect()
  echo = null
}
