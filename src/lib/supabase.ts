import "server-only";
import { createServerClient } from "@supabase/ssr";
import { cookies } from "next/headers";
import { supabaseSettings } from "./supabase-config";

export async function createClient() {
  const settings = supabaseSettings();
  if (!settings) throw new Error("Supabase environment is missing.");
  const cookieStore = await cookies();
  return createServerClient(settings.url, settings.key, {
    cookies: {
      getAll: () => cookieStore.getAll(),
      setAll(updates) {
        try {
          updates.forEach(({ name, value, options }) => cookieStore.set(name, value, options));
        } catch {
          // Server Components cannot write cookies. The request proxy refreshes them.
        }
      },
    },
  });
}
