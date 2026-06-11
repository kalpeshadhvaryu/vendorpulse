export type OrganizationRole = "member" | "owner" | "admin";

export type AccountType = "standard" | "global_admin";

export const ACCOUNT_TYPE_OPTIONS: { value: AccountType; label: string; description: string }[] = [
  {
    value: "standard",
    label: "Standard user",
    description: "Access is limited to assigned organizations.",
  },
  {
    value: "global_admin",
    label: "Global admin",
    description: "Can access all organizations and platform administration.",
  },
];

export const ORGANIZATION_ROLE_OPTIONS: { value: OrganizationRole; label: string }[] = [
  { value: "member", label: "Member" },
  { value: "admin", label: "Org admin" },
  { value: "owner", label: "Owner" },
];

export function accountTypeFromGlobalAdmin(isAdmin: boolean): AccountType {
  return isAdmin ? "global_admin" : "standard";
}

export function globalAdminFromAccountType(type: AccountType): boolean {
  return type === "global_admin";
}
