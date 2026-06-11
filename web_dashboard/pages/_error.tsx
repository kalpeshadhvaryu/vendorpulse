/**
 * Use Next.js built-in Pages error so `getInitialProps` and rendering always match what the dev server expects.
 * Custom wrappers here often fail to attach `getInitialProps` correctly after SWC transforms.
 */
export { default } from "next/error";
