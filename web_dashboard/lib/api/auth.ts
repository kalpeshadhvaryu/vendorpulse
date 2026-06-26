import { api } from "./client";
import type { ApiSuccessResponse, User } from "./types";

export async function loginRequest(payload: {
  email: string;
  password: string;
  device_name?: string;
}): Promise<{ token: string; user: User }> {
  const { data } = await api.post<
    ApiSuccessResponse<{ token: string; token_type: string; user: User }>
  >("/auth/login", {
    email: payload.email.trim().toLowerCase(),
    password: payload.password,
    device_name: payload.device_name ?? "VendorPulse Web",
  });
  return { token: data.data.token, user: data.data.user };
}

export async function fetchMe(): Promise<User> {
  const { data } = await api.get<ApiSuccessResponse<User>>("/auth/me");
  return data.data;
}

export async function logoutRequest(): Promise<void> {
  await api.post("/auth/logout");
}

export async function forgotPasswordRequest(email: string): Promise<string> {
  const { data } = await api.post<ApiSuccessResponse<null>>("/auth/forgot-password", {
    email: email.trim().toLowerCase(),
  });
  return data.message;
}

export async function resetPasswordRequest(payload: {
  email: string;
  token: string;
  password: string;
  password_confirmation: string;
}): Promise<string> {
  const { data } = await api.post<ApiSuccessResponse<null>>("/auth/reset-password", {
    email: payload.email.trim().toLowerCase(),
    token: payload.token,
    password: payload.password,
    password_confirmation: payload.password_confirmation,
  });
  return data.message;
}
