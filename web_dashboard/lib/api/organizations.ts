import { api } from "./client";
import type { ApiSuccessResponse, Organization, OrganizationDetail, User } from "./types";

export type OrganizationListResponse = ApiSuccessResponse<Organization[]>;

export async function fetchOrganizations(params?: { include_trashed?: boolean }): Promise<OrganizationListResponse> {
  const search = new URLSearchParams();
  if (params?.include_trashed) {
    search.set("include_trashed", "1");
  }
  const qs = search.toString();
  const { data } = await api.get<OrganizationListResponse>(`/organizations${qs ? `?${qs}` : ""}`);
  return data;
}

function unwrapResourcePayload<T extends Record<string, unknown>>(value: unknown): T | null {
  if (!value || typeof value !== "object") {
    return null;
  }

  const record = value as Record<string, unknown>;
  if ("id" in record) {
    return record as T;
  }

  if ("data" in record && record.data && typeof record.data === "object") {
    return record.data as T;
  }

  return null;
}

function normalizeOrganizationDetail(payload: unknown): OrganizationDetail | null {
  if (!payload || typeof payload !== "object") {
    return null;
  }

  const record = payload as Record<string, unknown>;

  const nestedOrganization = unwrapResourcePayload<Organization>(record.organization);
  if (nestedOrganization) {
    const membersRaw = record.members;
    const members = Array.isArray(membersRaw)
      ? membersRaw
      : membersRaw &&
          typeof membersRaw === "object" &&
          "data" in membersRaw &&
          Array.isArray((membersRaw as { data: unknown }).data)
        ? ((membersRaw as { data: User[] }).data ?? [])
        : [];

    return {
      organization: nestedOrganization,
      members,
    };
  }

  const bareOrganization = unwrapResourcePayload<Organization>(payload);
  if (bareOrganization) {
    return {
      organization: bareOrganization,
      members: [],
    };
  }

  return null;
}

export async function fetchOrganization(organizationId: string): Promise<ApiSuccessResponse<OrganizationDetail>> {
  const { data: body } = await api.get<ApiSuccessResponse<OrganizationDetail | Organization>>(
    `/organizations/${organizationId}`,
    { headers: { "X-Suppress-Server-Toast": "1" } },
  );

  const normalized = normalizeOrganizationDetail(body.data);
  if (!normalized) {
    throw new Error("Unexpected organization detail response from the API.");
  }

  return {
    ...body,
    data: normalized,
  };
}

export async function fetchOrganizationUsers(params?: { include_trashed?: boolean }): Promise<ApiSuccessResponse<User[]>> {
  const search = new URLSearchParams();
  if (params?.include_trashed) {
    search.set("include_trashed", "1");
  }
  const qs = search.toString();
  const { data } = await api.get<ApiSuccessResponse<User[]>>(`/organizations/users${qs ? `?${qs}` : ""}`, {
    headers: { "X-Suppress-Server-Toast": "1" },
  });
  return data;
}

function normalizeUser(payload: unknown): User | null {
  return unwrapResourcePayload<User>(payload);
}

export async function fetchOrganizationUser(userId: string): Promise<ApiSuccessResponse<User>> {
  const { data: body } = await api.get<ApiSuccessResponse<User>>(`/organizations/users/${userId}`, {
    headers: { "X-Suppress-Server-Toast": "1" },
  });

  const user = normalizeUser(body.data);
  if (!user) {
    throw new Error("Unexpected user detail response from the API.");
  }

  return {
    ...body,
    data: user,
  };
}

export type CreateOrganizationPayload = {
  name: string;
  slug?: string;
  phone_country_code?: string;
  phone_number?: string;
  owner_user_email?: string;
  set_owner_default?: boolean;
};

export async function createOrganization(payload: CreateOrganizationPayload): Promise<ApiSuccessResponse<Organization>> {
  const { data } = await api.post<ApiSuccessResponse<Organization>>("/organizations", payload);
  return data;
}

export type UpdateOrganizationPayload = {
  organizationId: string;
  name: string;
  slug: string;
  phone_country_code?: string;
  phone_number?: string;
};

export async function updateOrganization(payload: UpdateOrganizationPayload): Promise<ApiSuccessResponse<Organization>> {
  const { organizationId, ...body } = payload;
  const { data } = await api.patch<ApiSuccessResponse<Organization>>(`/organizations/${organizationId}`, body);
  return data;
}

export async function restoreOrganization(organizationId: string): Promise<ApiSuccessResponse<Organization>> {
  const { data } = await api.post<ApiSuccessResponse<Organization>>(`/organizations/${organizationId}/restore`);
  return data;
}

export type AssignOrganizationMemberPayload = {
  organizationId: string;
  user_email: string;
  role?: "member" | "owner" | "admin";
  set_default?: boolean;
};

export async function assignOrganizationMember(
  payload: AssignOrganizationMemberPayload,
): Promise<ApiSuccessResponse<null>> {
  const { organizationId, ...body } = payload;
  const { data } = await api.post<ApiSuccessResponse<null>>(`/organizations/${organizationId}/members`, body);
  return data;
}

export type UpdateOrganizationMemberPayload = {
  organizationId: string;
  userId: string;
  role: "member" | "owner" | "admin";
  set_default?: boolean;
};

export async function updateOrganizationMember(
  payload: UpdateOrganizationMemberPayload,
): Promise<ApiSuccessResponse<User>> {
  const { organizationId, userId, ...body } = payload;
  const { data } = await api.patch<ApiSuccessResponse<User>>(
    `/organizations/${organizationId}/members/${userId}`,
    body,
  );
  return data;
}

export async function detachOrganizationMember(organizationId: string, userId: string): Promise<void> {
  await api.delete(`/organizations/${organizationId}/members/${userId}`);
}

export type CreateOrganizationUserPayload = {
  organizationId: string;
  name: string;
  email: string;
  password: string;
  timezone?: string;
  role?: "member" | "owner" | "admin";
  is_admin?: boolean;
  set_default?: boolean;
  email_verified?: boolean;
};

export async function createOrganizationUser(
  payload: CreateOrganizationUserPayload,
): Promise<ApiSuccessResponse<User>> {
  const { organizationId, ...body } = payload;
  const { data } = await api.post<ApiSuccessResponse<User>>(`/organizations/${organizationId}/users`, body);
  return data;
}

export type UpdateOrganizationUserPayload = {
  userId: string;
  name?: string;
  email?: string;
  timezone?: string | null;
  password?: string;
  default_organization_id?: string | null;
};

export async function updateOrganizationUser(
  payload: UpdateOrganizationUserPayload,
): Promise<ApiSuccessResponse<User>> {
  const { userId, ...body } = payload;
  const { data } = await api.patch<ApiSuccessResponse<User>>(`/organizations/users/${userId}`, body);
  return data;
}

export async function deactivateOrganizationUser(userId: string): Promise<void> {
  await api.delete(`/organizations/users/${userId}`);
}

export async function restoreOrganizationUser(userId: string): Promise<ApiSuccessResponse<User>> {
  const { data } = await api.post<ApiSuccessResponse<User>>(`/organizations/users/${userId}/restore`);
  return data;
}

export async function updateUserGlobalAccess(payload: {
  userId: string;
  is_admin: boolean;
}): Promise<ApiSuccessResponse<User>> {
  const { userId, ...body } = payload;
  const { data } = await api.patch<ApiSuccessResponse<User>>(`/organizations/users/${userId}/global-access`, body);
  return data;
}

export async function softDeleteOrganization(organizationId: string): Promise<void> {
  await api.delete(`/organizations/${organizationId}`);
}

export async function permanentlyDeleteOrganization(organizationId: string): Promise<void> {
  await api.delete(`/organizations/${organizationId}/force`);
}
