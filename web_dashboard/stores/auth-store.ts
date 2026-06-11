import { create } from "zustand";
import { createJSONStorage, persist } from "zustand/middleware";
import type { Organization, User } from "@/lib/api/types";
import { clearSessionCookie, setSessionCookie } from "@/lib/auth-cookie";

type AuthState = {
  token: string | null;
  organizationId: string | null;
  user: User | null;
  setSession: (payload: { token: string; user: User }) => void;
  setOrganizationId: (id: string | null) => void;
  setUser: (user: User) => void;
  logout: () => Promise<void>;
};

function pickOrganizationId(user: User): string | null {
  const organizations = user.organizations ?? [];
  if (user.default_organization_id && organizations.some((org) => org.id === user.default_organization_id)) {
    return user.default_organization_id;
  }
  const first = organizations[0]?.id;
  return first ?? null;
}

/** In-memory storage for SSR / non-browser so persist never touches `localStorage` on the server. */
function createMemoryStorage(): Storage {
  const mem: Record<string, string> = {};
  return {
    get length() {
      return Object.keys(mem).length;
    },
    clear() {
      for (const k of Object.keys(mem)) delete mem[k];
    },
    getItem(key) {
      return mem[key] ?? null;
    },
    key(index) {
      return Object.keys(mem)[index] ?? null;
    },
    removeItem(key) {
      delete mem[key];
    },
    setItem(key, value) {
      mem[key] = value;
    },
  } as Storage;
}

function getPersistStorage(): Storage {
  if (typeof window === "undefined") {
    return createMemoryStorage();
  }
  return window.localStorage;
}

export const useAuthStore = create<AuthState>()(
  persist(
    (set, get) => ({
      token: null,
      organizationId: null,
      user: null,
      setSession: ({ token, user }) => {
        set({
          token,
          user,
          organizationId: pickOrganizationId(user),
        });
        setSessionCookie();
      },
      setOrganizationId: (id) => set({ organizationId: id }),
      setUser: (user) =>
        set((state) => {
          const previous = state.organizationId;
          const stillValid = previous !== null && (user.organizations ?? []).some((org) => org.id === previous);
          const keepAllForAdmin = previous === null && user.is_admin === true;

          return {
            user,
            organizationId: keepAllForAdmin ? null : stillValid ? previous : pickOrganizationId(user),
          };
        }),
      logout: async () => {
        const { token } = get();
        try {
          if (token) {
            const { logoutRequest } = await import("@/lib/api/auth");
            await logoutRequest();
          }
        } catch {
          /* ignore */
        } finally {
          clearSessionCookie();
          set({
            token: null,
            user: null,
            organizationId: null,
          });
        }
      },
    }),
    {
      name: "vendorpulse-auth",
      storage: createJSONStorage(getPersistStorage),
      partialize: (state) => ({
        token: state.token,
        organizationId: state.organizationId,
        user: state.user,
      }),
    },
  ),
);

export function useOrganizations(): Organization[] {
  return useAuthStore((s) => s.user?.organizations ?? []);
}
