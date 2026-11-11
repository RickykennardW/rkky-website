import { Shell } from "@/components/shell";
import { getWorkspaceIdentity } from "@/lib/data";

export const dynamic = "force-dynamic";

export default async function WorkspaceLayout({ children }: { children: React.ReactNode }) {
  const { profile, preview } = await getWorkspaceIdentity();
  return <Shell profile={profile} preview={preview}>{children}</Shell>;
}
