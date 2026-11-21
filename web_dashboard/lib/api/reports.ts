import { api } from "./client";

export type ReportPdfType =
  | "port-checker"
  | "url-checker"
  | "dns-check"
  | "website-speedtest"
  | "vapt"
  | "on-page-seo"
  | "security-audit";

function triggerBlobDownload(blob: Blob, fileName: string): void {
  const url = URL.createObjectURL(blob);
  const anchor = document.createElement("a");
  anchor.href = url;
  anchor.download = fileName;
  document.body.appendChild(anchor);
  anchor.click();
  anchor.remove();
  URL.revokeObjectURL(url);
}

function fileNameFromDisposition(header: string | undefined, fallback: string): string {
  if (!header) return fallback;
  const match = /filename\*?=(?:UTF-8''|")?([^\";]+)"?/i.exec(header);
  if (!match?.[1]) return fallback;
  try {
    return decodeURIComponent(match[1].replace(/"/g, "").trim());
  } catch {
    return match[1].replace(/"/g, "").trim() || fallback;
  }
}

async function ensurePdfBlob(data: Blob, contentTypeHeader?: string): Promise<Blob> {
  const contentType = (contentTypeHeader || data.type || "").toLowerCase();
  if (contentType.includes("application/pdf") || contentType.includes("octet-stream")) {
    return data.type ? data : new Blob([data], { type: "application/pdf" });
  }

  // API errors come back as JSON even when responseType is blob.
  const text = await data.text();
  try {
    const json = JSON.parse(text) as { message?: string; error?: string };
    throw new Error(json.message || json.error || "PDF download failed.");
  } catch (error) {
    if (error instanceof Error && error.message !== "PDF download failed." && !error.message.includes("JSON")) {
      throw error;
    }
    if (text.trim().startsWith("%PDF")) {
      return new Blob([text], { type: "application/pdf" });
    }
    throw new Error(text || "PDF download failed.");
  }
}

export async function downloadReportPdf(
  type: ReportPdfType,
  report: unknown,
  options?: {
    fileName?: string;
    meta?: { subtitle?: string; target?: string; organization?: string };
  },
): Promise<void> {
  const fallback = `${type}-${new Date().toISOString().slice(0, 19).replace(/[:T]/g, "-")}.pdf`;
  try {
    const response = await api.post(
      `/reports/pdf/${type}`,
      {
        report,
        meta: options?.meta,
        file_name: options?.fileName ?? fallback,
      },
      { responseType: "blob" },
    );

    const blob = await ensurePdfBlob(
      response.data as Blob,
      String(response.headers["content-type"] || ""),
    );
    triggerBlobDownload(
      blob,
      fileNameFromDisposition(response.headers["content-disposition"], options?.fileName ?? fallback),
    );
  } catch (error: unknown) {
    const maybeAxios = error as { response?: { data?: Blob }; message?: string };
    if (maybeAxios.response?.data instanceof Blob) {
      const blob = maybeAxios.response.data;
      const text = await blob.text();
      try {
        const json = JSON.parse(text) as { message?: string };
        throw new Error(json.message || "PDF download failed.");
      } catch (parsed) {
        if (parsed instanceof Error && parsed.message !== "PDF download failed.") {
          throw parsed;
        }
        throw new Error(text || maybeAxios.message || "PDF download failed.");
      }
    }
    throw error;
  }
}

export async function downloadVaptReportPdf(
  testId: string,
  params?: { from?: string; to?: string },
): Promise<void> {
  const search = new URLSearchParams();
  if (params?.from) search.set("from", params.from);
  if (params?.to) search.set("to", params.to);
  const qs = search.toString();
  try {
    const response = await api.get(
      `/experience-monitoring-tests/${testId}/report.pdf${qs ? `?${qs}` : ""}`,
      { responseType: "blob" },
    );
    const blob = await ensurePdfBlob(
      response.data as Blob,
      String(response.headers["content-type"] || ""),
    );
    triggerBlobDownload(
      blob,
      fileNameFromDisposition(response.headers["content-disposition"], `vapt-${testId}.pdf`),
    );
  } catch (error: unknown) {
    const maybeAxios = error as { response?: { data?: Blob }; message?: string };
    if (maybeAxios.response?.data instanceof Blob) {
      const text = await maybeAxios.response.data.text();
      try {
        const json = JSON.parse(text) as { message?: string };
        throw new Error(json.message || "PDF download failed.");
      } catch (parsed) {
        if (parsed instanceof Error && parsed.message !== "PDF download failed.") {
          throw parsed;
        }
        throw new Error(text || maybeAxios.message || "PDF download failed.");
      }
    }
    throw error;
  }
}
