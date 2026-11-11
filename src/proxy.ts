import { createServerClient } from "@supabase/ssr";
import { NextResponse, type NextRequest } from "next/server";
import { supabaseSettings } from "@/lib/supabase-config";

export async function proxy(request: NextRequest) {
  const settings = supabaseSettings();
  let response = NextResponse.next({ request });
  if (!settings) return response;
  const client = createServerClient(settings.url, settings.key, {
    cookies: {
      getAll: () => request.cookies.getAll(),
      setAll(updates, headers) {
        updates.forEach(({ name, value }) => request.cookies.set(name, value));
        response = NextResponse.next({ request });
        updates.forEach(({ name, value, options }) => response.cookies.set(name, value, options));
        if (headers) {
          new Headers(headers).forEach((value, name) => response.headers.set(name, value));
        }
      },
    },
  });
  await client.auth.getClaims();
  return response;
}

export const config = {
  matcher: ["/((?!_next/static|_next/image|favicon.ico|.*\\.(?:svg|png|jpg|jpeg|gif|webp|woff2)$).*)"],
};
