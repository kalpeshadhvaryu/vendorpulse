/**
 * Pages Router 404 fallback when App `not-found` cannot be compiled in dev.
 */
export default function Pages404() {
  return (
    <div
      style={{
        minHeight: "100vh",
        display: "flex",
        flexDirection: "column",
        alignItems: "center",
        justifyContent: "center",
        padding: 24,
        fontFamily: "ui-sans-serif, system-ui, sans-serif",
        background: "#fafafa",
        color: "#18181b",
      }}
    >
      <h1 style={{ fontSize: "1.25rem", fontWeight: 600 }}>404 — Page not found</h1>
      <a href="/login" style={{ marginTop: "1rem", fontSize: "0.875rem", color: "#4f46e5" }}>
        Sign in
      </a>
      <a href="/dashboard" style={{ marginTop: "0.5rem", fontSize: "0.875rem", color: "#4f46e5" }}>
        Dashboard
      </a>
    </div>
  );
}
