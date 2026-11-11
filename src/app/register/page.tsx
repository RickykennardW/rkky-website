import { AuthScreen } from "@/components/auth-screen";
export const metadata = { title: "Create an account" };
export const dynamic = "force-dynamic";
export default function Register() { return <AuthScreen registration />; }
