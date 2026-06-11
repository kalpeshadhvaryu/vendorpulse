import type { AppProps } from "next/app";

/**
 * Minimal Pages Router shell so dev can always resolve `/_error` and `/404`
 * when the App Router error UI is not ready yet (e.g. file watcher / EMFILE issues).
 */
export default function App({ Component, pageProps }: AppProps) {
  return <Component {...pageProps} />;
}
