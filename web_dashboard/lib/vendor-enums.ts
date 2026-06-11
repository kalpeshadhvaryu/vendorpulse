/** Mirrors `App\Enums\VendorType` (string values). */
export const VENDOR_TYPE_VALUES = [
  "hosting",
  "dns",
  "saas",
  "hardware",
  "telecom",
  "utility",
  "other",
] as const;

export type VendorTypeValue = (typeof VENDOR_TYPE_VALUES)[number];

export const VENDOR_TYPES: { value: VendorTypeValue; label: string }[] = [
  { value: "hosting", label: "Hosting" },
  { value: "dns", label: "DNS" },
  { value: "saas", label: "SaaS" },
  { value: "hardware", label: "Hardware" },
  { value: "telecom", label: "Telecom" },
  { value: "utility", label: "Utility" },
  { value: "other", label: "Other" },
];

/** Mirrors `App\Enums\BillingCycle`. */
export const BILLING_CYCLE_VALUES = [
  "monthly",
  "quarterly",
  "semi_annual",
  "annual",
  "biennial",
  "one_time",
  "custom",
] as const;

export type BillingCycleValue = (typeof BILLING_CYCLE_VALUES)[number];

export const BILLING_CYCLES: { value: BillingCycleValue; label: string }[] = [
  { value: "monthly", label: "Monthly" },
  { value: "quarterly", label: "Quarterly" },
  { value: "semi_annual", label: "Semi-annual" },
  { value: "annual", label: "Annual" },
  { value: "biennial", label: "Biennial" },
  { value: "one_time", label: "One-time" },
  { value: "custom", label: "Custom" },
];

/** Mirrors `App\Enums\VendorStatus`. */
export const VENDOR_STATUS_VALUES = [
  "active",
  "inactive",
  "pending",
  "suspended",
  "churned",
] as const;

export type VendorStatusValue = (typeof VENDOR_STATUS_VALUES)[number];

export const VENDOR_STATUSES: { value: VendorStatusValue; label: string }[] = [
  { value: "active", label: "Active" },
  { value: "inactive", label: "Inactive" },
  { value: "pending", label: "Pending" },
  { value: "suspended", label: "Suspended" },
  { value: "churned", label: "Churned" },
];
