import Link from "next/link";
import { Activity, ArrowRight, BellRing, Radar, Server, ShieldCheck, Sparkles, Zap } from "lucide-react";

const signalBars = [22, 48, 34, 76, 58, 92, 64, 88, 52, 70, 40, 84];

const liveSignals = [
  { label: "Uptime", value: "99.98%", tone: "text-emerald-300" },
  { label: "Checks/sec", value: "1.2k", tone: "text-cyan-300" },
  { label: "Alerts", value: "04", tone: "text-amber-300" },
  { label: "Queue lag", value: "< 10s", tone: "text-violet-300" },
];

const workflowNodes = [
  { label: "Vendor inbox", detail: "Email extraction and normalization" },
  { label: "Monitoring engine", detail: "Health checks, retries, and alerts" },
  { label: "Execution log", detail: "Live history with run summaries" },
];

export default function HomePage() {
  return (
    <main className="relative overflow-hidden bg-[#050816] text-slate-100">
      <div className="absolute inset-0 bg-[radial-gradient(circle_at_top_left,rgba(99,102,241,0.28),transparent_30%),radial-gradient(circle_at_top_right,rgba(16,185,129,0.18),transparent_26%),radial-gradient(circle_at_bottom,rgba(14,165,233,0.18),transparent_30%)]" />
      <div className="absolute inset-0 bg-[linear-gradient(to_right,rgba(148,163,184,0.08)_1px,transparent_1px),linear-gradient(to_bottom,rgba(148,163,184,0.08)_1px,transparent_1px)] bg-[size:72px_72px] opacity-20" />
      <div className="absolute left-1/2 top-24 h-[32rem] w-[32rem] -translate-x-1/2 rounded-full border border-cyan-400/20 blur-3xl animate-radar-glow" />

      <div className="relative mx-auto flex min-h-screen w-full max-w-7xl flex-col px-6 py-8 lg:px-10">
        <header className="flex items-center justify-between gap-4">
          <div className="flex items-center gap-3">
            <div className="flex h-11 w-11 items-center justify-center rounded-2xl border border-cyan-400/25 bg-slate-950/80 shadow-[0_0_30px_rgba(34,211,238,0.18)]">
              <ShieldCheck className="h-5 w-5 text-cyan-300" />
            </div>
            <div>
              <p className="text-xs uppercase tracking-[0.35em] text-cyan-200/70">VendorPulse</p>
              <p className="text-sm text-slate-300">Monitoring SaaS for vendors, invoices, and renewals</p>
            </div>
          </div>
          <div className="flex items-center gap-3 text-sm">
            <Link
              href="/login"
              className="rounded-full border border-white/10 bg-white/5 px-4 py-2 text-slate-200 transition hover:border-cyan-300/40 hover:bg-cyan-400/10"
            >
              Sign in
            </Link>
            <Link
              href="/dashboard"
              className="inline-flex items-center gap-2 rounded-full bg-cyan-300 px-4 py-2 font-semibold text-slate-950 shadow-[0_0_28px_rgba(103,232,249,0.28)] transition hover:bg-cyan-200"
            >
              Open dashboard
              <ArrowRight className="h-4 w-4" />
            </Link>
          </div>
        </header>

        <section className="grid flex-1 items-center gap-10 py-10 lg:grid-cols-[1.15fr_0.85fr] lg:py-16">
          <div className="relative z-10 space-y-8">
            <div className="inline-flex items-center gap-2 rounded-full border border-cyan-300/20 bg-cyan-300/10 px-4 py-2 text-sm text-cyan-100 shadow-[0_0_30px_rgba(34,211,238,0.12)] backdrop-blur">
              <Sparkles className="h-4 w-4" />
              Real-time vendor operations, designed like a mission control panel
            </div>

            <div className="space-y-5">
              <h1 className="max-w-3xl text-5xl font-semibold tracking-tight text-white sm:text-6xl lg:text-7xl">
                A living monitoring surface for every vendor event.
              </h1>
              <p className="max-w-2xl text-lg leading-8 text-slate-300 sm:text-xl">
                Track renewals, watch inbox-driven extraction, run site checks, and inspect execution history from a single SaaS command center.
              </p>
            </div>

            <div className="flex flex-wrap gap-4">
              <Link
                href="/login"
                className="inline-flex items-center gap-2 rounded-full bg-white px-6 py-3 font-semibold text-slate-950 transition hover:bg-cyan-100"
              >
                Start monitoring
                <Zap className="h-4 w-4" />
              </Link>
              <Link
                href="/dashboard"
                className="inline-flex items-center gap-2 rounded-full border border-white/10 bg-white/5 px-6 py-3 font-semibold text-slate-100 transition hover:border-cyan-300/40 hover:bg-white/10"
              >
                View live metrics
                <Activity className="h-4 w-4" />
              </Link>
            </div>

            <div className="grid gap-4 sm:grid-cols-2">
              {liveSignals.map((signal) => (
                <div
                  key={signal.label}
                  className="rounded-3xl border border-white/10 bg-white/5 p-5 backdrop-blur-xl shadow-[0_18px_60px_rgba(2,6,23,0.35)]"
                >
                  <p className="text-xs uppercase tracking-[0.3em] text-slate-400">{signal.label}</p>
                  <p className={`mt-2 text-3xl font-semibold ${signal.tone}`}>{signal.value}</p>
                  <div className="mt-4 h-1.5 overflow-hidden rounded-full bg-slate-800">
                    <div className="h-full rounded-full bg-gradient-to-r from-cyan-400 via-emerald-400 to-violet-400 animate-flow" />
                  </div>
                </div>
              ))}
            </div>
          </div>

          <div className="relative mx-auto w-full max-w-xl">
            <div className="absolute inset-0 rounded-[2rem] bg-cyan-400/10 blur-3xl animate-float" />
            <div className="relative overflow-hidden rounded-[2rem] border border-white/10 bg-slate-950/75 p-5 shadow-[0_30px_120px_rgba(2,6,23,0.6)] backdrop-blur-2xl">
              <div className="flex items-center justify-between border-b border-white/10 pb-4">
                <div>
                  <p className="text-sm font-medium text-slate-100">Live monitoring radar</p>
                  <p className="text-xs text-slate-400">Activity pulses update in real time</p>
                </div>
                <div className="flex items-center gap-2 rounded-full border border-emerald-400/20 bg-emerald-400/10 px-3 py-1 text-xs text-emerald-200">
                  <span className="h-2 w-2 rounded-full bg-emerald-300 shadow-[0_0_18px_rgba(52,211,153,0.9)] animate-pulse" />
                  System healthy
                </div>
              </div>

              <div className="relative mt-6 overflow-hidden rounded-[1.75rem] border border-cyan-300/15 bg-[radial-gradient(circle_at_center,rgba(34,211,238,0.13),rgba(15,23,42,0.92)_55%)] p-6">
                <div className="absolute inset-0 bg-[radial-gradient(circle_at_center,transparent_0%,transparent_46%,rgba(34,211,238,0.12)_47%,transparent_48%,transparent_63%,rgba(99,102,241,0.12)_64%,transparent_65%)] animate-spin-slow opacity-90" />
                <div className="absolute inset-0 bg-[linear-gradient(to_right,transparent_0%,rgba(34,211,238,0.18)_49%,rgba(34,211,238,0.48)_50%,rgba(34,211,238,0.18)_51%,transparent_100%)] animate-radar-sweep opacity-75" />

                <div className="relative flex min-h-[24rem] flex-col justify-between">
                  <div className="flex items-start justify-between gap-4">
                    <div className="rounded-2xl border border-white/10 bg-slate-950/60 px-4 py-3">
                      <p className="text-xs uppercase tracking-[0.28em] text-slate-400">Alert stream</p>
                      <p className="mt-1 text-sm text-slate-200">4 checks completed in the last minute</p>
                    </div>
                    <div className="rounded-2xl border border-white/10 bg-slate-950/60 px-4 py-3 text-right">
                      <p className="text-xs uppercase tracking-[0.28em] text-slate-400">Next sweep</p>
                      <p className="mt-1 text-sm text-cyan-200">00:14 sec</p>
                    </div>
                  </div>

                  <div className="mx-auto flex items-center gap-4">
                    <div className="grid h-28 w-28 place-items-center rounded-full border border-cyan-300/25 bg-cyan-300/10 shadow-[0_0_60px_rgba(34,211,238,0.15)] animate-pulse">
                      <Radar className="h-10 w-10 text-cyan-200" />
                    </div>
                    <div className="space-y-3">
                      {workflowNodes.map((node, index) => (
                        <div key={node.label} className="flex items-center gap-3">
                          <div className="relative flex h-3 w-3 items-center justify-center">
                            <span className="absolute inline-flex h-5 w-5 rounded-full bg-cyan-300/30 animate-ping" />
                            <span className="relative h-3 w-3 rounded-full bg-cyan-300 shadow-[0_0_18px_rgba(103,232,249,0.85)]" />
                          </div>
                          <div className="rounded-2xl border border-white/10 bg-white/5 px-4 py-3">
                            <p className="text-sm font-medium text-slate-100">{node.label}</p>
                            <p className="text-xs text-slate-400">{node.detail}</p>
                          </div>
                          <span className="text-xs text-slate-500">0{index + 1}</span>
                        </div>
                      ))}
                    </div>
                  </div>

                  <div className="grid grid-cols-12 gap-2">
                    {signalBars.map((height, index) => (
                      <div key={index} className="col-span-1 flex items-end justify-center">
                        <span
                          className="w-full rounded-t-full bg-gradient-to-t from-cyan-400 via-emerald-300 to-violet-300 shadow-[0_0_18px_rgba(34,211,238,0.18)] animate-bar-rise"
                          style={{ height: `${height}%`, animationDelay: `${index * 80}ms` }}
                        />
                      </div>
                    ))}
                  </div>
                </div>
              </div>

              <div className="mt-5 grid gap-3 sm:grid-cols-3">
                <div className="rounded-2xl border border-white/10 bg-white/5 p-4">
                  <p className="text-xs uppercase tracking-[0.28em] text-slate-400">Queued runs</p>
                  <p className="mt-2 text-2xl font-semibold text-white">18</p>
                </div>
                <div className="rounded-2xl border border-white/10 bg-white/5 p-4">
                  <p className="text-xs uppercase tracking-[0.28em] text-slate-400">Recovered</p>
                  <p className="mt-2 text-2xl font-semibold text-emerald-300">+12%</p>
                </div>
                <div className="rounded-2xl border border-white/10 bg-white/5 p-4">
                  <p className="text-xs uppercase tracking-[0.28em] text-slate-400">Open alerts</p>
                  <p className="mt-2 text-2xl font-semibold text-amber-300">3</p>
                </div>
              </div>
            </div>
          </div>
        </section>

        <section className="grid gap-4 pb-6 md:grid-cols-3">
          <div className="rounded-3xl border border-white/10 bg-white/5 p-5 backdrop-blur-xl">
            <BellRing className="h-5 w-5 text-cyan-300" />
            <h2 className="mt-4 text-lg font-semibold text-white">Queue-aware alerts</h2>
            <p className="mt-2 text-sm leading-6 text-slate-400">
              See run requests, retries, and failures as they move through the monitoring pipeline.
            </p>
          </div>
          <div className="rounded-3xl border border-white/10 bg-white/5 p-5 backdrop-blur-xl">
            <Server className="h-5 w-5 text-emerald-300" />
            <h2 className="mt-4 text-lg font-semibold text-white">Operational clarity</h2>
            <p className="mt-2 text-sm leading-6 text-slate-400">
              Inspect vendor health, invoice workflows, and scheduler status from one interface.
            </p>
          </div>
          <div className="rounded-3xl border border-white/10 bg-white/5 p-5 backdrop-blur-xl">
            <Activity className="h-5 w-5 text-violet-300" />
            <h2 className="mt-4 text-lg font-semibold text-white">Live execution history</h2>
            <p className="mt-2 text-sm leading-6 text-slate-400">
              Review history logs with a visual pulse instead of a flat admin table.
            </p>
          </div>
        </section>

        <footer className="mt-6 rounded-3xl border border-white/10 bg-slate-950/70 p-6 backdrop-blur-xl sm:p-8">
          <div className="grid gap-8 border-b border-white/10 pb-8 lg:grid-cols-[1.35fr_repeat(3,minmax(0,1fr))]">
            <div className="space-y-4">
              <div className="flex items-center gap-3">
                <div className="flex h-10 w-10 items-center justify-center rounded-xl border border-cyan-300/30 bg-cyan-300/10 text-cyan-200">
                  <ShieldCheck className="h-5 w-5" />
                </div>
                <div>
                  <p className="text-sm font-semibold text-white">Vendor Pulse</p>
                  <p className="text-xs text-slate-400">VeravalOnline Private Limited</p>
                </div>
              </div>
              <p className="max-w-md text-sm leading-6 text-slate-400">
                Create, monitor, and approve workflows securely in your own Vendor Pulse workspace.
              </p>
            </div>

            <div>
              <p className="text-xs font-semibold uppercase tracking-[0.24em] text-cyan-200/90">Product</p>
              <div className="mt-4 flex flex-col gap-2 text-sm text-slate-300">
                <Link href="/dashboard" className="transition hover:text-cyan-200">
                  Dashboards
                </Link>
                <Link href="/docs" className="transition hover:text-cyan-200">
                  Documents
                </Link>
                <Link href="/monitoring" className="transition hover:text-cyan-200">
                  Monitoring
                </Link>
                <Link href="/invoices" className="transition hover:text-cyan-200">
                  Invoices
                </Link>
              </div>
            </div>

            <div>
              <p className="text-xs font-semibold uppercase tracking-[0.24em] text-cyan-200/90">Company</p>
              <div className="mt-4 flex flex-col gap-2 text-sm text-slate-300">
                <Link href="/" className="transition hover:text-cyan-200">
                  About
                </Link>
                <a
                  href="mailto:sales@veravalonline.com?subject=Careers%20at%20Vendor%20Pulse"
                  className="transition hover:text-cyan-200"
                >
                  Careers
                </a>
                <Link href="/docs" className="transition hover:text-cyan-200">
                  Blog
                </Link>
                <a href="mailto:sales@veravalonline.com" className="transition hover:text-cyan-200">
                  Contact
                </a>
              </div>
            </div>

            <div>
              <p className="text-xs font-semibold uppercase tracking-[0.24em] text-cyan-200/90">Support</p>
              <div className="mt-4 flex flex-col gap-2 text-sm text-slate-300">
                <Link href="/docs/getting-started" className="transition hover:text-cyan-200">
                  Help Center
                </Link>
                <Link href="/monitoring" className="transition hover:text-cyan-200">
                  Status
                </Link>
                <Link href="/docs/configure" className="transition hover:text-cyan-200">
                  Privacy
                </Link>
                <Link href="/docs/api" className="transition hover:text-cyan-200">
                  Terms
                </Link>
              </div>
            </div>
          </div>

          <div className="flex flex-col gap-3 pt-5 text-sm text-slate-400 sm:flex-row sm:items-center sm:justify-between">
            <div className="flex flex-wrap items-center gap-3">
              <a href="mailto:sales@veravalonline.com" className="transition hover:text-cyan-200">
                sales@veravalonline.com
              </a>
              <span className="hidden text-slate-600 sm:inline">|</span>
              <a href="tel:+917863042495" className="transition hover:text-cyan-200">
                +91-7863042495
              </a>
            </div>
            <p className="text-xs text-slate-500">© 2026 VeravalOnline Private Limited. All rights reserved.</p>
          </div>
        </footer>
      </div>
    </main>
  );
}
