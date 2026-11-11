import { NextResponse, type NextRequest } from "next/server";
import { createClient } from "@/lib/supabase";
import { supabaseSettings } from "@/lib/supabase-config";

export async function GET(request: NextRequest) {
  if (!supabaseSettings()) return NextResponse.redirect(new URL("/setup", request.url));
  const code = request.nextUrl.searchParams.get("code");
  const tokenHash = request.nextUrl.searchParams.get("token_hash");
  const client = await createClient();
  const result = code ? await client.auth.exchangeCodeForSession(code)
    : tokenHash ? await client.auth.verifyOtp({ token_hash: tokenHash, type: "email" }) : null;
  return NextResponse.redirect(new URL(result && !result.error ? "/" : "/login?confirmation=failed", request.url));
}
