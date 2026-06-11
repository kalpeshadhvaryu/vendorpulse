"use client";

import type { UseFormReturn } from "react-hook-form";
import { useFieldArray } from "react-hook-form";
import { z } from "zod";
import { Label } from "@/components/ui/label";
import { Input } from "@/components/ui/input";
import {
  BILLING_CYCLE_VALUES,
  BILLING_CYCLES,
  VENDOR_STATUSES,
  VENDOR_STATUS_VALUES,
  VENDOR_TYPES,
  VENDOR_TYPE_VALUES,
} from "@/lib/vendor-enums";
import type { VendorWritePayload } from "@/lib/api/vendors";
import type { Vendor } from "@/lib/api/types";
import { Button } from "@/components/ui/button";
import { cn } from "@/lib/utils";

const INBOUND_MAIL_PURPOSES = [
  { value: "general", label: "General" },
  { value: "primary", label: "Primary" },
  { value: "billing", label: "Billing" },
  { value: "alerts", label: "Alerts" },
  { value: "abuse", label: "Abuse" },
] as const;

const selectClass = cn(
  "flex h-9 w-full rounded-md border border-input bg-background px-3 py-1 text-sm text-foreground shadow-sm",
  "focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring",
  "disabled:cursor-not-allowed disabled:opacity-50",
);

const textareaClass = cn(
  "flex min-h-[88px] w-full rounded-md border border-input bg-background px-3 py-2 text-sm text-foreground shadow-sm",
  "placeholder:text-muted-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring",
  "disabled:cursor-not-allowed disabled:opacity-50",
);

export const vendorFormSchema = z.object({
  name: z.string().min(1, "Name is required").max(255),
  vendor_type: z.enum(VENDOR_TYPE_VALUES),
  billing_email: z.string().max(255).refine(
    (s) => s.trim() === "" || z.string().email().safeParse(s.trim()).success,
    "Invalid email",
  ),
  support_email: z.string().max(255).refine(
    (s) => s.trim() === "" || z.string().email().safeParse(s.trim()).success,
    "Invalid email",
  ),
  website: z.string().max(2048).refine(
    (s) => s.trim() === "" || z.string().url().safeParse(s.trim()).success,
    "Invalid URL",
  ),
  currency: z
    .string()
    .length(3, "Use a 3-letter code")
    .regex(/^[A-Za-z]{3}$/, "Letters only"),
  expected_amount: z.string().refine((s) => {
    const t = s.trim();
    if (t === "") return true;
    const n = Number(t);
    return !Number.isNaN(n) && n >= 0 && n <= 999999999999.99;
  }, "Invalid amount"),
  billing_cycle: z.enum(BILLING_CYCLE_VALUES),
  renewal_date: z.string().refine(
    (s) => s.trim() === "" || /^\d{4}-\d{2}-\d{2}$/.test(s.trim()),
    "Use YYYY-MM-DD",
  ),
  auto_detect_invoices: z.boolean(),
  auto_fetch_email: z.boolean(),
  match_inbound_from_website_domain: z.boolean(),
  monitoring_enabled: z.boolean(),
  notes: z.string(),
  status: z.enum(VENDOR_STATUS_VALUES),
  inbound_emails: z
    .array(
      z.object({
        email: z.string().max(255),
        label: z.string().max(255),
        purpose: z.enum(["general", "primary", "billing", "alerts", "abuse"]),
      }),
    )
    .max(25),
}).superRefine((data, ctx) => {
  data.inbound_emails.forEach((row, i) => {
    const e = row.email.trim();
    if (e === "") {
      return;
    }
    if (!z.string().email().safeParse(e).success) {
      ctx.addIssue({
        code: z.ZodIssueCode.custom,
        message: "Invalid email",
        path: ["inbound_emails", i, "email"],
      });
    }
  });
});

export type VendorFormValues = z.infer<typeof vendorFormSchema>;

export const emptyVendorFormDefaults: VendorFormValues = {
  name: "",
  vendor_type: "other",
  billing_email: "",
  support_email: "",
  website: "",
  currency: "USD",
  expected_amount: "",
  billing_cycle: "monthly",
  renewal_date: "",
  auto_detect_invoices: false,
  auto_fetch_email: true,
  match_inbound_from_website_domain: false,
  monitoring_enabled: false,
  notes: "",
  status: "active",
  inbound_emails: [{ email: "", label: "", purpose: "general" }],
};

export function vendorToFormValues(v: Vendor): VendorFormValues {
  return {
    name: v.name,
    vendor_type: z.enum(VENDOR_TYPE_VALUES).safeParse(v.vendor_type).success
      ? (v.vendor_type as VendorFormValues["vendor_type"])
      : "other",
    billing_email: v.billing_email ?? "",
    support_email: v.support_email ?? "",
    website: v.website ?? "",
    currency: v.currency,
    expected_amount: v.expected_amount != null && v.expected_amount !== "" ? String(v.expected_amount) : "",
    billing_cycle: z.enum(BILLING_CYCLE_VALUES).safeParse(v.billing_cycle).success
      ? (v.billing_cycle as VendorFormValues["billing_cycle"])
      : "monthly",
    renewal_date: v.renewal_date ? v.renewal_date.slice(0, 10) : "",
    auto_detect_invoices: v.auto_detect_invoices,
    auto_fetch_email: v.auto_fetch_email ?? true,
    match_inbound_from_website_domain: v.match_inbound_from_website_domain ?? false,
    monitoring_enabled: v.monitoring_enabled,
    notes: v.notes ?? "",
    status: z.enum(VENDOR_STATUS_VALUES).safeParse(v.status).success
      ? (v.status as VendorFormValues["status"])
      : "active",
    inbound_emails: [{ email: "", label: "", purpose: "general" }],
  };
}

export function vendorFormValuesToPayload(values: VendorFormValues): VendorWritePayload {
  const billing_email = values.billing_email.trim();
  const support_email = values.support_email.trim();
  const website = values.website.trim();
  const renewal = values.renewal_date.trim();
  const notes = values.notes.trim();
  const expected = values.expected_amount.trim();

  return {
    name: values.name.trim(),
    vendor_type: values.vendor_type,
    billing_cycle: values.billing_cycle,
    currency: values.currency.trim().toUpperCase(),
    auto_detect_invoices: values.auto_detect_invoices,
    auto_fetch_email: values.auto_fetch_email,
    match_inbound_from_website_domain: values.match_inbound_from_website_domain,
    monitoring_enabled: values.monitoring_enabled,
    status: values.status,
    billing_email: billing_email === "" ? null : billing_email,
    support_email: support_email === "" ? null : support_email,
    website: website === "" ? null : website,
    renewal_date: renewal === "" ? null : renewal,
    notes: notes === "" ? null : notes,
    expected_amount: expected === "" ? null : Number(expected),
  };
}

type Props = {
  form: UseFormReturn<VendorFormValues>;
  /** When true (Add vendor), show optional incoming-address rows saved after the vendor is created. */
  showInboundStaging?: boolean;
};

export function VendorFormFields({ form, showInboundStaging = false }: Props) {
  const {
    register,
    control,
    formState: { errors },
  } = form;

  const inboundFieldArray = useFieldArray({
    control,
    name: "inbound_emails",
  });

  return (
    <div className="flex flex-col gap-4">
      <div className="space-y-2">
        <Label htmlFor="vendor-name">Name</Label>
        <Input id="vendor-name" autoComplete="organization" {...register("name")} />
        {errors.name && <p className="text-xs text-destructive">{errors.name.message}</p>}
      </div>

      <div className="grid gap-4 sm:grid-cols-2">
        <div className="space-y-2">
          <Label htmlFor="vendor-type">Type</Label>
          <select id="vendor-type" className={selectClass} {...register("vendor_type")}>
            {VENDOR_TYPES.map((o) => (
              <option key={o.value} value={o.value}>
                {o.label}
              </option>
            ))}
          </select>
          {errors.vendor_type && (
            <p className="text-xs text-destructive">{errors.vendor_type.message}</p>
          )}
        </div>
        <div className="space-y-2">
          <Label htmlFor="vendor-status">Status</Label>
          <select id="vendor-status" className={selectClass} {...register("status")}>
            {VENDOR_STATUSES.map((o) => (
              <option key={o.value} value={o.value}>
                {o.label}
              </option>
            ))}
          </select>
          {errors.status && <p className="text-xs text-destructive">{errors.status.message}</p>}
        </div>
      </div>

      <div className="grid gap-4 sm:grid-cols-2">
        <div className="space-y-2">
          <Label htmlFor="vendor-billing-email">Billing email</Label>
          <Input id="vendor-billing-email" type="email" autoComplete="email" {...register("billing_email")} />
          {errors.billing_email && (
            <p className="text-xs text-destructive">{errors.billing_email.message}</p>
          )}
        </div>
        <div className="space-y-2">
          <Label htmlFor="vendor-support-email">Support email</Label>
          <Input id="vendor-support-email" type="email" {...register("support_email")} />
          {errors.support_email && (
            <p className="text-xs text-destructive">{errors.support_email.message}</p>
          )}
        </div>
      </div>

      {showInboundStaging ? (
        <div className="flex flex-col gap-3 rounded-md border border-border/60 p-3">
          <div>
            <h3 className="text-sm font-semibold">Incoming mail addresses (optional)</h3>
            <p className="mt-1 text-xs text-muted-foreground">
              Exact From / To / Cc match per address—no shared subject tag. Add trusted vendor inboxes now, or add
              more later under <strong className="text-foreground">Edit vendor</strong>.
            </p>
          </div>
          <div className="flex flex-col gap-4">
            {inboundFieldArray.fields.map((field, index) => (
              <div
                key={field.id}
                className="flex flex-col gap-3 border-b border-border/40 pb-4 last:border-b-0 last:pb-0 sm:flex-row sm:flex-wrap sm:items-end"
              >
                <div className="min-w-0 flex-1 space-y-1.5">
                  <Label htmlFor={`inbound-email-${field.id}`}>Match address</Label>
                  <Input
                    id={`inbound-email-${field.id}`}
                    type="email"
                    placeholder="accounts@vendor.com"
                    autoComplete="off"
                    {...register(`inbound_emails.${index}.email` as const)}
                  />
                  {errors.inbound_emails?.[index]?.email ? (
                    <p className="text-xs text-destructive">{errors.inbound_emails[index]?.email?.message}</p>
                  ) : null}
                </div>
                <div className="w-full space-y-1.5 sm:w-40">
                  <Label htmlFor={`inbound-label-${field.id}`}>Label (optional)</Label>
                  <Input
                    id={`inbound-label-${field.id}`}
                    placeholder="AP inbox"
                    {...register(`inbound_emails.${index}.label` as const)}
                  />
                </div>
                <div className="w-full space-y-1.5 sm:w-36">
                  <Label htmlFor={`inbound-purpose-${field.id}`}>Purpose</Label>
                  <select
                    id={`inbound-purpose-${field.id}`}
                    className={selectClass}
                    {...register(`inbound_emails.${index}.purpose` as const)}
                  >
                    {INBOUND_MAIL_PURPOSES.map((p) => (
                      <option key={p.value} value={p.value}>
                        {p.label}
                      </option>
                    ))}
                  </select>
                </div>
                <div className="flex gap-2 sm:shrink-0">
                  {inboundFieldArray.fields.length > 1 ? (
                    <Button
                      type="button"
                      variant="outline"
                      size="sm"
                      className="h-9 text-xs"
                      onClick={() => inboundFieldArray.remove(index)}
                    >
                      Remove
                    </Button>
                  ) : null}
                </div>
              </div>
            ))}
          </div>
          {inboundFieldArray.fields.length < 25 ? (
            <Button
              type="button"
              variant="outline"
              size="sm"
              className="self-start border-dashed text-xs"
              onClick={() => inboundFieldArray.append({ email: "", label: "", purpose: "general" })}
            >
              Add another address
            </Button>
          ) : null}
        </div>
      ) : null}

      <div className="space-y-2">
        <Label htmlFor="vendor-website">Website</Label>
        <Input id="vendor-website" type="url" placeholder="https://…" {...register("website")} />
        {errors.website && <p className="text-xs text-destructive">{errors.website.message}</p>}
      </div>

      <div className="grid gap-4 sm:grid-cols-2">
        <div className="space-y-2">
          <Label htmlFor="vendor-currency">Currency</Label>
          <Input id="vendor-currency" maxLength={3} className="uppercase" {...register("currency")} />
          {errors.currency && <p className="text-xs text-destructive">{errors.currency.message}</p>}
        </div>
        <div className="space-y-2">
          <Label htmlFor="vendor-expected">Expected amount</Label>
          <Input id="vendor-expected" inputMode="decimal" placeholder="Optional" {...register("expected_amount")} />
          {errors.expected_amount && (
            <p className="text-xs text-destructive">{errors.expected_amount.message}</p>
          )}
        </div>
      </div>

      <div className="grid gap-4 sm:grid-cols-2">
        <div className="space-y-2">
          <Label htmlFor="vendor-billing-cycle">Billing cycle</Label>
          <select id="vendor-billing-cycle" className={selectClass} {...register("billing_cycle")}>
            {BILLING_CYCLES.map((o) => (
              <option key={o.value} value={o.value}>
                {o.label}
              </option>
            ))}
          </select>
          {errors.billing_cycle && (
            <p className="text-xs text-destructive">{errors.billing_cycle.message}</p>
          )}
        </div>
        <div className="space-y-2">
          <Label htmlFor="vendor-renewal">Renewal date</Label>
          <Input id="vendor-renewal" type="date" {...register("renewal_date")} />
          {errors.renewal_date && (
            <p className="text-xs text-destructive">{errors.renewal_date.message}</p>
          )}
        </div>
      </div>

      <div className="flex flex-col gap-3 rounded-md border border-border/60 p-3">
        <div className="space-y-1">
          <label className="flex cursor-pointer items-center gap-2 text-sm font-medium">
            <input type="checkbox" className="size-4 rounded border-input" {...register("auto_fetch_email")} />
            Auto-fetch email
          </label>
          <p className="pl-6 text-xs text-muted-foreground">
            When a message matches this vendor, run attachment registration, OCR, and downstream email automation.
            Mailbox polling stays organization-wide; this only affects matched mail for this vendor.
          </p>
        </div>
        <div className="space-y-1">
          <label className="flex cursor-pointer items-center gap-2 text-sm font-medium">
            <input type="checkbox" className="size-4 rounded border-input" {...register("auto_detect_invoices")} />
            Auto-detect invoices
          </label>
          <p className="pl-6 text-xs text-muted-foreground">
            When automation runs, extract invoice candidates (amount, due date, etc.) from the email body.
          </p>
        </div>
        <div className="space-y-1">
          <label className="flex cursor-pointer items-center gap-2 text-sm font-medium">
            <input
              type="checkbox"
              className="size-4 rounded border-input"
              {...register("match_inbound_from_website_domain")}
            />
            Match by vendor website domain
          </label>
          <p className="pl-6 text-xs text-muted-foreground">
            Optional fallback: if the sender&apos;s domain appears in this vendor&apos;s website URL, treat the
            message as this vendor. Off by default so unrelated mail on the same domain is not attributed. Prefer
            explicit addresses in Incoming mail (above when adding a vendor, or under Edit vendor).
          </p>
        </div>
        <label className="flex cursor-pointer items-center gap-2 text-sm">
          <input type="checkbox" className="size-4 rounded border-input" {...register("monitoring_enabled")} />
          Monitoring enabled
        </label>
      </div>

      <div className="space-y-2">
        <Label htmlFor="vendor-notes">Notes</Label>
        <textarea id="vendor-notes" className={textareaClass} rows={4} {...register("notes")} />
        {errors.notes && <p className="text-xs text-destructive">{errors.notes.message}</p>}
      </div>
    </div>
  );
}
