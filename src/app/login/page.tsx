import { AuthScreen } from "@/components/auth-screen";
export const metadata = { title: "Sign in" };
export default async function Login({ searchParams }: { searchParams: Promise<{ confirmation?: string }> }) {
  return <AuthScreen failed={(await searchParams).confirmation === "failed"} />;
}
