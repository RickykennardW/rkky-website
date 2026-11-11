import "server-only";

export function supabaseSettings() {
  const url = process.env.NEXT_PUBLIC_SUPABASE_URL?.trim();
  const key = process.env.NEXT_PUBLIC_SUPABASE_PUBLISHABLE_KEY?.trim();
  if (!url || !/^https?:\/\//i.test(url) || !URL.canParse(url)) return null;
  if (!key || !/^sb_publishable_[A-Za-z0-9_-]+$/.test(key)) return null;
  return { url, key };
}
