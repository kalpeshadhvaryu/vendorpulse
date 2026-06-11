"use client";

import { create } from "zustand";
import { createJSONStorage, persist } from "zustand/middleware";

export const LIST_PAGE_SIZE_OPTIONS = [15, 25, 50, 100] as const;

export type ListPageSize = (typeof LIST_PAGE_SIZE_OPTIONS)[number];

function coercePageSize(value: unknown, fallback: ListPageSize): ListPageSize {
  const n = typeof value === "number" ? value : Number(value);
  return LIST_PAGE_SIZE_OPTIONS.includes(n as ListPageSize) ? (n as ListPageSize) : fallback;
}

type DashboardSettingsState = {
  vendorsPerPage: ListPageSize;
  invoicesPerPage: ListPageSize;
  monitoringPerPage: ListPageSize;
  notificationsPerPage: ListPageSize;
  setVendorsPerPage: (n: ListPageSize) => void;
  setInvoicesPerPage: (n: ListPageSize) => void;
  setMonitoringPerPage: (n: ListPageSize) => void;
  setNotificationsPerPage: (n: ListPageSize) => void;
};

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

export const useDashboardSettings = create<DashboardSettingsState>()(
  persist(
    (set) => ({
      vendorsPerPage: 25,
      invoicesPerPage: 50,
      monitoringPerPage: 50,
      notificationsPerPage: 50,
      setVendorsPerPage: (n) => set({ vendorsPerPage: coercePageSize(n, 25) }),
      setInvoicesPerPage: (n) => set({ invoicesPerPage: coercePageSize(n, 50) }),
      setMonitoringPerPage: (n) => set({ monitoringPerPage: coercePageSize(n, 50) }),
      setNotificationsPerPage: (n) => set({ notificationsPerPage: coercePageSize(n, 50) }),
    }),
    {
      name: "vendorpulse-dashboard-settings",
      storage: createJSONStorage(getPersistStorage),
      partialize: (s) => ({
        vendorsPerPage: s.vendorsPerPage,
        invoicesPerPage: s.invoicesPerPage,
        monitoringPerPage: s.monitoringPerPage,
        notificationsPerPage: s.notificationsPerPage,
      }),
    },
  ),
);
