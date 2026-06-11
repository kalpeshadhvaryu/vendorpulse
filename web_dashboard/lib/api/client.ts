import axios, { type AxiosInstance } from "axios";
import { useAuthStore } from "@/stores/auth-store";
import { getApiErrorMessage } from "./errors";
import { publicApiBaseUrl } from "./public-url";

export { publicApiBaseUrl, publicApiUrlFromEnv } from "./public-url";

export const api: AxiosInstance = axios.create({
  baseURL: publicApiBaseUrl,
  headers: {
    Accept: "application/json",
    "Content-Type": "application/json",
  },
});

api.interceptors.request.use((config) => {
  const { token, organizationId } = useAuthStore.getState();
  if (token) {
    config.headers.Authorization = `Bearer ${token}`;
  }
  const explicitOrganizationId =
    config.headers["X-Organization-Id"] ?? config.headers["x-organization-id"];
  if (explicitOrganizationId) {
    config.headers["X-Organization-Id"] = String(explicitOrganizationId);
  } else if (organizationId) {
    config.headers["X-Organization-Id"] = organizationId;
  } else {
    delete config.headers["X-Organization-Id"];
  }
  return config;
});

api.interceptors.response.use(
  (res) => res,
  (error) => {
    const status = error.response?.status;
    const suppressServerToast = error.config?.headers?.["X-Suppress-Server-Toast"] === "1";
    if (status === 401) {
      useAuthStore.getState().logout();
    }
    // Avoid importing `sonner` at module scope: it is pulled into the SSR graph for any route
    // that imports this client, and can contribute to hard-to-debug server failures.
    if (typeof window !== "undefined" && status && status >= 500 && !suppressServerToast) {
      void import("sonner").then(({ toast }) => {
        toast.error(getApiErrorMessage(error));
      });
    }
    return Promise.reject(error);
  },
);
