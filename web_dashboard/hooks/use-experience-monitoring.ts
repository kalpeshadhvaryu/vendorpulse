import { useQuery } from "@tanstack/react-query";
import { queryKeys } from "@/lib/api/query-keys";
import {
  fetchExperienceMonitoringMetrics,
  fetchExperienceMonitoringReport,
  fetchExperienceMonitoringRuns,
  fetchExperienceMonitoringScreenshots,
  fetchExperienceMonitoringTest,
  fetchExperienceMonitoringTests,
} from "@/lib/api/experience-monitoring";

export function useExperienceMonitoringTests(params: Record<string, string>) {
  return useQuery({
    queryKey: queryKeys.experienceMonitoringTests(params),
    queryFn: () => fetchExperienceMonitoringTests(params),
  });
}

export function useExperienceMonitoringTest(testId: string) {
  return useQuery({
    queryKey: queryKeys.experienceMonitoringTest(testId),
    queryFn: () => fetchExperienceMonitoringTest(testId),
    enabled: Boolean(testId),
  });
}

export function useExperienceMonitoringRuns(testId: string, params: Record<string, string>) {
  return useQuery({
    queryKey: queryKeys.experienceMonitoringRuns(testId, params),
    queryFn: () => fetchExperienceMonitoringRuns(testId, params),
    enabled: Boolean(testId),
  });
}

export function useExperienceMonitoringMetrics(testId: string, params: Record<string, string>) {
  return useQuery({
    queryKey: queryKeys.experienceMonitoringMetrics(testId, params),
    queryFn: () => fetchExperienceMonitoringMetrics(testId, params),
    enabled: Boolean(testId),
  });
}

export function useExperienceMonitoringScreenshots(testId: string, params: Record<string, string>) {
  return useQuery({
    queryKey: queryKeys.experienceMonitoringScreenshots(testId, params),
    queryFn: () => fetchExperienceMonitoringScreenshots(testId, params),
    enabled: Boolean(testId),
  });
}

export function useExperienceMonitoringReport(testId: string, params: Record<string, string>) {
  return useQuery({
    queryKey: queryKeys.experienceMonitoringReport(testId, params),
    queryFn: () => fetchExperienceMonitoringReport(testId, params),
    enabled: Boolean(testId),
  });
}
