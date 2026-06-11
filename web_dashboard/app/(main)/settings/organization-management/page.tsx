import { redirect } from "next/navigation";

export default function LegacyOrganizationManagementPage() {
  redirect("/admin/organizations");
}
