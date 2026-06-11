"use client";

import type { Organization } from "@/lib/api/types";
import {
  ACCOUNT_TYPE_OPTIONS,
  type AccountType,
  ORGANIZATION_ROLE_OPTIONS,
  type OrganizationRole,
} from "@/lib/admin/user-access";
import { adminSelectClass } from "@/lib/admin/helpers";
import { Label } from "@/components/ui/label";

type UserAccessFieldsProps = {
  accountType: AccountType;
  onAccountTypeChange: (value: AccountType) => void;
  organizationId: string;
  onOrganizationIdChange: (value: string) => void;
  organizations: Organization[];
  organizationRole: OrganizationRole;
  onOrganizationRoleChange: (value: OrganizationRole) => void;
  disabled?: boolean;
  organizationRequired?: boolean;
};

export function UserAccessFields({
  accountType,
  onAccountTypeChange,
  organizationId,
  onOrganizationIdChange,
  organizations,
  organizationRole,
  onOrganizationRoleChange,
  disabled = false,
  organizationRequired = true,
}: UserAccessFieldsProps) {
  const selectedAccountType = ACCOUNT_TYPE_OPTIONS.find((option) => option.value === accountType);

  return (
    <div className="space-y-4 rounded-md border border-border/60 bg-muted/20 p-4">
      <div>
        <p className="text-sm font-semibold">Access & role</p>
        <p className="text-xs text-muted-foreground">
          Account type is platform-wide. Role applies to the selected organization membership.
        </p>
      </div>

      <div className="grid gap-2">
        <Label>Account type</Label>
        <select
          className={adminSelectClass}
          value={accountType}
          onChange={(e) => onAccountTypeChange(e.target.value as AccountType)}
          disabled={disabled}
        >
          {ACCOUNT_TYPE_OPTIONS.map((option) => (
            <option key={option.value} value={option.value}>
              {option.label}
            </option>
          ))}
        </select>
        {selectedAccountType ? (
          <p className="text-xs text-muted-foreground">{selectedAccountType.description}</p>
        ) : null}
      </div>

      <div className="grid gap-2">
        <Label>Organization{organizationRequired ? "" : " (optional)"}</Label>
        <select
          className={adminSelectClass}
          value={organizationId}
          onChange={(e) => onOrganizationIdChange(e.target.value)}
          disabled={disabled || organizations.length === 0}
        >
          <option value="">{organizationRequired ? "Select organization…" : "None"}</option>
          {organizations.map((org) => (
            <option key={org.id} value={org.id}>
              {org.name}
            </option>
          ))}
        </select>
      </div>

      <div className="grid gap-2">
        <Label>Role</Label>
        <select
          className={adminSelectClass}
          value={organizationRole}
          onChange={(e) => onOrganizationRoleChange(e.target.value as OrganizationRole)}
          disabled={disabled || !organizationId}
        >
          {ORGANIZATION_ROLE_OPTIONS.map((option) => (
            <option key={option.value} value={option.value}>
              {option.label}
            </option>
          ))}
        </select>
        <p className="text-xs text-muted-foreground">
          Organization role: member (day-to-day), org admin (org settings), or owner (full control in that org).
        </p>
      </div>
    </div>
  );
}
